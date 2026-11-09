<?php

declare(strict_types=1);

namespace App\Domains\Identity\UserManagement\Http\Controllers;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\UserManagement\Contracts\SuperAdminCooperativeAssignmentService;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class SuperAdminCooperativeAssignmentController
{
    public function __construct(
        private readonly SuperAdminCooperativeAssignmentService $userManagementService,
    ) {}

    public function index(User $superAdmin): View
    {
        // L'espace Root doit rester inaccessible aux autres rôles, même si
        // un super_admin peut consulter ses propres affectations dans son espace.
        if (request()->routeIs('root.backoffice.users.cooperatives.*')) {
            abort_unless($this->isSuperAdminRoot(request()->user()), 403);
        }

        Gate::authorize('viewAssignments', $superAdmin);

        $cooperatives = $superAdmin->cooperatives()
            ->with('address.country')
            ->orderBy('cooperatives.name')
            ->paginate(15, ['*'], 'assigned_page');

        $availableCooperatives = $this->isSuperAdminRoot(request()->user())
            ? Cooperative::query()
                ->with('address.country')
                ->whereDoesntHave(
                    'superAdmins',
                    static fn (Builder $query) => $query->whereKey($superAdmin->getKey()),
                )
                ->orderBy('name')
                ->paginate(15, ['*'], 'available_page')
            : new LengthAwarePaginator(
                collect(),
                0,
                15,
                1,
                [
                    'path' => request()->url(),
                    'pageName' => 'available_page',
                ],
            );

        $view = $this->isSuperAdminRoot(request()->user())
            ? 'super-admin-root.users.cooperatives'
            : 'backoffice.super-admins.cooperatives.index';

        return view($view, [
            'superAdmin' => $superAdmin,
            'cooperatives' => $cooperatives,
            'availableCooperatives' => $availableCooperatives,
        ]);
    }

    public function store(
        Request $request,
        User $superAdmin,
        Cooperative $cooperative,
    ): RedirectResponse {
        Gate::authorize('assignCooperative', [$superAdmin, $cooperative]);

        try {
            $this->userManagementService->assignSuperAdminToCooperative(
                $request->user(),
                $superAdmin,
                $cooperative,
            );
        } catch (\DomainException) {
            return redirect()
                ->route($this->assignmentRouteName($request), $superAdmin)
                ->with('error', 'Cette affectation n’est plus autorisée. Actualisez la page et vérifiez le statut du super administrateur.');
        } catch (QueryException $exception) {
            report($exception);

            return redirect()
                ->route($this->assignmentRouteName($request), $superAdmin)
                ->with('error', 'Un conflit de base de données a empêché l’affectation. Aucune modification partielle n’a été conservée.');
        }

        return redirect()
            ->route($this->assignmentRouteName($request), $superAdmin)
            ->with('success', 'La coopérative a été affectée au super administrateur.');
    }

    public function destroy(
        Request $request,
        User $superAdmin,
        Cooperative $cooperative,
    ): RedirectResponse {
        Gate::authorize('unassignCooperative', [$superAdmin, $cooperative]);

        try {
            $this->userManagementService->removeSuperAdminFromCooperative(
                $request->user(),
                $superAdmin,
                $cooperative,
            );
        } catch (\DomainException) {
            return redirect()
                ->route($this->assignmentRouteName($request), $superAdmin)
                ->with('error', 'Cette désaffectation n’est plus autorisée. Actualisez la page et vérifiez le statut du super administrateur.');
        } catch (QueryException $exception) {
            report($exception);

            return redirect()
                ->route($this->assignmentRouteName($request), $superAdmin)
                ->with('error', 'Un conflit de base de données a empêché la désaffectation. Aucune modification partielle n’a été conservée.');
        }

        return redirect()
            ->route($this->assignmentRouteName($request), $superAdmin)
            ->with('success', 'L’affectation de la coopérative a été retirée.');
    }

    private function assignmentRouteName(Request $request): string
    {
        return $this->isSuperAdminRoot($request->user())
            ? 'root.backoffice.users.cooperatives.index'
            : 'backoffice.super-admins.cooperatives.index';
    }

    private function isSuperAdminRoot(?User $user): bool
    {
        return $user !== null
            && $user->isAuthorized()
            && $user->roles()
                ->where('slug', 'super_admin_root')
                ->exists();
    }
}
