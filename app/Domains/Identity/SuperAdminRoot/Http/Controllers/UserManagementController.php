<?php

declare(strict_types=1);

namespace App\Domains\Identity\SuperAdminRoot\Http\Controllers;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Authentication\Models\AuthenticationSession;
use App\Domains\Identity\UserManagement\Exceptions\UserInvitationDeliveryException;
use App\Domains\Identity\UserManagement\Http\Requests\CreateUserRequest;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Identity\UserManagement\Services\UserInvitationService;
use App\Domains\Identity\UserManagement\Services\UserManagementService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

final class UserManagementController
{
    public function create(Request $request): View
    {
        $this->authorizeRoot($request);
        Gate::authorize('create', User::class);

        $actor = $request->user();

        $allowedRoles = $actor->hasRole('super_admin_root')
            ? ['super_admin', 'cooperative_admin', 'user']
            : ['cooperative_admin', 'user'];

        $roles = Role::query()
            ->where('is_active', true)
            ->whereIn('slug', $allowedRoles)
            ->orderBy('name')
            ->get(['name', 'slug']);

        $cooperatives = Cooperative::query()
            ->visibleTo($actor)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('super-admin-root.users.create', [
            'roles' => $roles,
            'cooperatives' => $cooperatives,
        ]);
    }

    public function edit(Request $request, User $user): View
    {
        $this->authorizeRoot($request);
        Gate::authorize('view', $user);

        abort_if($user->roles()->where('slug', 'super_admin_root')->exists(), 404);

        $user->load(['roles', 'cooperative']);

        return view('super-admin-root.users.edit', [
            'user' => $user,
            'roles' => Role::query()
                ->where('is_active', true)
                ->whereIn('slug', ['super_admin', 'cooperative_admin', 'user'])
                ->orderBy('name')
                ->get(['name', 'slug']),
            'cooperatives' => Cooperative::query()
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function update(
        Request $request,
        User $user,
        UserManagementService $userManagementService,
        UserInvitationService $invitationService,
    ): RedirectResponse {
        $this->authorizeRoot($request);
        Gate::authorize('view', $user);
        abort_if($user->roles()->where('slug', 'super_admin_root')->exists(), 404);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->getKey())],
            'telephone' => ['required', 'string', 'max:255'],
            'role_slug' => ['required', Rule::in(['super_admin', 'cooperative_admin', 'user'])],
            'cooperative_id' => [
                Rule::requiredIf(fn (): bool => $request->input('role_slug') !== 'super_admin'),
                'nullable',
                'string',
                Rule::exists('cooperatives', 'id')->whereNull('deleted_at'),
            ],
        ]);

        $roleSlug = $data['role_slug'];
        unset($data['role_slug']);

        try {
            $emailChanged = $user->email !== $data['email'];
            $updatedUser = $userManagementService->updateUserAccount(
                $request->user(),
                $user,
                $data,
                $roleSlug,
            );

            if ($emailChanged && in_array($updatedUser->status, [UserStatus::Active, UserStatus::Pending], true)) {
                $invitationService->sendInvitation(
                    $updatedUser,
                    $updatedUser->status === UserStatus::Active ? 'root_manual_verification' : 'account_activation',
                );
            }
        } catch (UserInvitationDeliveryException) {
            return redirect()
                ->route('root.backoffice.users.show', $user)
                ->with('error', 'Le compte a été modifié, mais le nouvel e-mail de vérification n’a pas pu être envoyé.');
        } catch (\DomainException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('root.backoffice.users.show', $user)
            ->with('success', $emailChanged
                ? 'Le compte a été mis à jour. La nouvelle adresse doit être vérifiée ; une invitation a été envoyée si le statut du compte le permet. Les anciens accès ont été révoqués.'
                : 'Le compte et son rôle ont été mis à jour. Les anciennes sessions et les anciens jetons API ont été révoqués.');
    }

    public function store(
        CreateUserRequest $request,
        UserManagementService $userManagementService,
        UserInvitationService $invitationService,
    ): RedirectResponse {
        $this->authorizeRoot($request);
        $data = $request->validated();

        $roleSlug = $data['role_slug'];
        $initialStatus = UserStatus::from($data['status']);
        unset($data['role_slug'], $data['status']);

        try {
            $user = $userManagementService->createUser(
                $request->user(),
                $data,
                $roleSlug,
                $initialStatus,
            );

            $invitationService->sendInvitation(
                $user,
                $user->status === UserStatus::Active ? 'root_manual_verification' : 'account_activation',
            );
        } catch (UserInvitationDeliveryException) {
            return back()->with(
                'error',
                'Le compte a été créé, mais l’invitation n’a pas pu être envoyée. Le compte reste en attente ; vous pourrez renvoyer l’invitation.',
            );
        } catch (\DomainException $exception) {
            return back()
                ->withInput()
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('root.backoffice.users.index')
            ->with(
                'success',
                'Le compte a été créé. Une invitation a été envoyée à son adresse e-mail.',
            );
    }

    public function resendInvitation(
        Request $request,
        User $user,
        UserInvitationService $invitationService,
    ): RedirectResponse {
        $this->authorizeRoot($request);
        Gate::authorize('resendInvitation', $user);

        try {
            $invitationService->sendInvitation(
                $user,
                $user->status === UserStatus::Active ? 'root_manual_verification' : 'account_activation',
            );
        } catch (UserInvitationDeliveryException) {
            return redirect()
                ->route('root.backoffice.users.show', $user)
                ->with(
                    'error',
                    'L’invitation n’a pas pu être envoyée. Le compte reste actif mais non vérifié ; vous pourrez réessayer.',
                );
        } catch (\DomainException) {
            return redirect()
                ->route('root.backoffice.users.show', $user)
                ->with(
                    'error',
                    'L’invitation n’a pas pu être renvoyée : le compte est déjà vérifié, indisponible ou son statut ne permet plus l’invitation.',
                );
        }

        return redirect()
            ->route('root.backoffice.users.show', $user)
            ->with(
                'success',
                'Une nouvelle invitation a été envoyée à l’adresse e-mail de cet utilisateur.',
            );
    }

    public function show(Request $request, User $user): View
    {
        $this->authorizeRoot($request);
        Gate::authorize('view', $user);

        $user->load([
            'roles' => static fn ($query) => $query->orderBy('name'),
            'cooperative',
        ]);

        $canViewAssignments = Gate::allows('viewAssignments', $user);

        if ($canViewAssignments) {
            $user->load('cooperatives');
        }

        $sessions = AuthenticationSession::query()
            ->where('user_id', $user->getKey())
            ->whereNull('revoked_at')
            ->orderByDesc('last_activity_at')
            ->get();

        $currentSessionId = $request->session()->getId();
        $sessions->each(static function (AuthenticationSession $session) use ($currentSessionId): void {
            $session->setAttribute('is_current', hash_equals((string) $session->session_id, $currentSessionId));
        });

        return view('super-admin-root.users.show', [
            'user' => $user,
            'canViewAssignments' => $canViewAssignments,
            'sessions' => $sessions,
        ]);
    }

    public function activate(
        Request $request,
        User $user,
        UserManagementService $userManagementService,
        UserInvitationService $invitationService,
    ): RedirectResponse {
        $this->authorizeRoot($request);
        Gate::authorize('activate', $user);

        try {
            $activeUser = $userManagementService->activateUser($request->user(), $user);
            $invitationService->sendInvitation($activeUser, 'root_manual_verification');
        } catch (UserInvitationDeliveryException) {
            return redirect()
                ->route('root.backoffice.users.show', $user)
                ->with('error', 'Le compte est actif, mais l’e-mail de vérification n’a pas pu être envoyé. Vous pourrez réessayer.');
        } catch (\DomainException $exception) {
            return redirect()
                ->route('root.backoffice.users.show', $user)
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('root.backoffice.users.show', $user)
            ->with('success', 'Le compte est actif, mais la connexion reste bloquée jusqu’à la vérification de l’adresse e-mail. Une invitation a été envoyée.');
    }

    public function activateAndVerify(
        Request $request,
        User $user,
        UserManagementService $userManagementService,
    ): RedirectResponse {
        $this->authorizeRoot($request);
        Gate::authorize('activate', $user);

        try {
            $userManagementService->activateAndVerifyUser($request->user(), $user);
        } catch (\DomainException $exception) {
            return redirect()->route('root.backoffice.users.show', $user)
                ->with('error', $exception->getMessage());
        }

        return redirect()->route('root.backoffice.users.show', $user)
            ->with('success', 'Le compte est actif et l’e-mail est vérifié. Le mot de passe provisoire est MahlineMahline ; son remplacement sera obligatoire à la première connexion.');
    }

    public function deactivate(
        Request $request,
        User $user,
        UserManagementService $userManagementService,
    ): RedirectResponse {
        $this->authorizeRoot($request);
        Gate::authorize('deactivate', $user);

        try {
            $userManagementService->deactivateUser($request->user(), $user);
        } catch (\DomainException) {
            return redirect()
                ->route('root.backoffice.users.show', $user)
                ->with('error', 'Le statut du compte a changé ou cette transition n’est plus autorisée.');
        }

        return redirect()
            ->route('root.backoffice.users.show', $user)
            ->with('success', 'Le compte utilisateur a été désactivé. Ses sessions et jetons API ont été révoqués.');
    }

    public function suspend(
        Request $request,
        User $user,
        UserManagementService $userManagementService,
    ): RedirectResponse {
        $this->authorizeRoot($request);
        Gate::authorize('suspend', $user);

        try {
            $userManagementService->suspendUser($request->user(), $user);
        } catch (\DomainException) {
            return redirect()
                ->route('root.backoffice.users.show', $user)
                ->with('error', 'Le statut du compte a changé ou cette transition n’est plus autorisée.');
        }

        return redirect()
            ->route('root.backoffice.users.show', $user)
            ->with('success', 'Le compte utilisateur a été suspendu. Ses sessions et jetons API ont été révoqués.');
    }

    public function archive(
        Request $request,
        User $user,
        UserManagementService $userManagementService,
    ): RedirectResponse {
        $this->authorizeRoot($request);
        Gate::authorize('archive', $user);

        try {
            $userManagementService->archiveUser($request->user(), $user);
        } catch (\DomainException) {
            return redirect()
                ->route('root.backoffice.users.show', $user)
                ->with('error', 'Le compte ne peut plus être archivé : son état a changé ou cette action n’est plus autorisée.');
        }

        return redirect()
            ->route('root.backoffice.users.show', $user)
            ->with('success', 'Le compte a été archivé. Ses sessions et jetons API ont été révoqués.');
    }

    public function restore(
        Request $request,
        User $user,
        UserManagementService $userManagementService,
    ): RedirectResponse {
        $this->authorizeRoot($request);
        Gate::authorize('restore', $user);

        try {
            $userManagementService->restoreArchivedUser($request->user(), $user);
        } catch (UserInvitationDeliveryException) {
            return redirect()
                ->route('root.backoffice.users.show', $user)
                ->with('error', 'Le compte a été restauré en attente, mais la nouvelle invitation n’a pas pu être envoyée. Vous pourrez réessayer.');
        } catch (\DomainException $exception) {
            return redirect()
                ->route('root.backoffice.users.show', $user)
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('root.backoffice.users.show', $user)
            ->with('success', 'Le compte a été restauré en attente. Choisissez ensuite l’une des deux méthodes d’activation. Les anciens accès restent révoqués.');
    }

    public function destroy(Request $request, User $user, UserManagementService $service): RedirectResponse
    {
        $this->authorizeRoot($request);
        Gate::authorize('delete', $user);

        try {
            $service->deleteUser($request->user(), $user);
        } catch (\DomainException) {
            return redirect()->route('root.backoffice.users.index')
                ->with('error', 'Le compte ne peut pas être supprimé : son état a changé ou cette action n’est plus autorisée.');
        }

        return redirect()->route('root.backoffice.users.index')
            ->with('success', 'Le compte a été supprimé logiquement. Ses sessions et jetons API ont été révoqués.');
    }

    public function restoreDeleted(
        Request $request,
        string $user,
        UserManagementService $service,
    ): RedirectResponse {
        $this->authorizeRoot($request);
        $deletedUser = User::withTrashed()->findOrFail($user);
        Gate::authorize('restoreDeleted', $deletedUser);

        try {
            $service->restoreDeletedUser($request->user(), $deletedUser);
        } catch (UserInvitationDeliveryException) {
            return redirect()->route('root.backoffice.users.index')
                ->with('error', 'Le compte a été restauré en attente, mais la nouvelle invitation n’a pas pu être envoyée. Vous pourrez réessayer.');
        } catch (\DomainException $exception) {
            return redirect()->route('root.backoffice.users.index', ['deleted' => 1])
                ->with('error', $exception->getMessage());
        }

        return redirect()->route('root.backoffice.users.index')
            ->with('success', 'Le compte a été restauré en attente. Choisissez maintenant une méthode d’activation. Les anciens accès restent révoqués.');
    }

    public function index(Request $request): View
    {
        $this->authorizeRoot($request);
        Gate::authorize('viewAny', User::class);

        $filters = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'role' => ['sometimes', 'nullable', 'string', 'exists:roles,slug'],
            'status' => ['sometimes', 'nullable', 'string'],
            'deleted' => ['sometimes', 'nullable', 'in:0,1'],
        ]);

        $validStatuses = array_map(
            static fn (UserStatus $status): string => $status->value,
            UserStatus::cases(),
        );

        if (isset($filters['status']) && $filters['status'] !== '') {
            abort_unless(in_array($filters['status'], $validStatuses, true), 422);
        }

        $actor = $request->user();
        $isRoot = $actor->hasRole('super_admin_root');
        $deletedOnly = ($filters['deleted'] ?? '0') === '1';

        abort_if($deletedOnly && ! $isRoot, 403);

        $users = User::query()
            ->when($deletedOnly, static fn (Builder $query) => $query->onlyTrashed())
            ->when(
                ! $isRoot && $actor->hasRole('super_admin'),
                static fn (Builder $query) => $query->whereIn(
                    'cooperative_id',
                    $actor->cooperatives()->select('cooperatives.id'),
                ),
            )
            ->when(
                ! $isRoot && $actor->hasRole('cooperative_admin'),
                static fn (Builder $query) => $query->where('cooperative_id', $actor->cooperative_id),
            )
            ->with([
                'roles' => static fn ($query) => $query->orderBy('name'),
                'cooperative',
            ])
            ->when(
                filled($filters['search'] ?? null),
                static function (Builder $query) use ($filters): void {
                    $term = trim($filters['search']);

                    $query->where(static function (Builder $query) use ($term): void {
                        $query
                            ->where('first_name', 'like', '%'.$term.'%')
                            ->orWhere('last_name', 'like', '%'.$term.'%')
                            ->orWhere('display_name', 'like', '%'.$term.'%')
                            ->orWhere('email', 'like', '%'.$term.'%')
                            ->orWhere('telephone', 'like', '%'.$term.'%');
                    });
                },
            )
            ->when(
                filled($filters['role'] ?? null),
                static fn (Builder $query) => $query->whereHas(
                    'roles',
                    static fn (Builder $roles) => $roles->where('slug', $filters['role']),
                ),
            )
            ->when(
                filled($filters['status'] ?? null),
                static fn (Builder $query) => $query->where('status', $filters['status']),
            )
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(15)
            ->withQueryString();

        return view('super-admin-root.users.index', [
            'users' => $users,
            'roles' => Role::query()->where('is_active', true)->orderBy('name')->get(['name', 'slug']),
            'statuses' => UserStatus::cases(),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'role' => $filters['role'] ?? '',
                'status' => $filters['status'] ?? '',
                'deleted' => $deletedOnly ? '1' : '0',
            ],
        ]);
    }
    private function authorizeRoot(Request $request): void
    {
        abort_unless($request->user()?->hasRole('super_admin_root'), 403);
    }
}
