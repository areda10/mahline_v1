<?php

declare(strict_types=1);

namespace App\Domains\Identity\UserManagement\Http\Controllers;

use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class UserManagementController
{
    public function show(Request $request, User $user): View
    {
        abort_unless(
            $request->user()->hasRole('super_admin_root'),
            403,
        );

        Gate::authorize('view', $user);

        $user->load([
            'roles' => static fn ($query) => $query->orderBy('name'),
            'cooperative',
        ]);

        $isSuperAdmin = $user->roles->contains(
            static fn ($role): bool => $role->slug === 'super_admin' && $role->is_active,
        );

        if ($isSuperAdmin) {
            Gate::authorize('viewAssignments', $user);
            $user->load('cooperatives');
        }

        return view('backoffice.users.show', [
            'user' => $user,
            'isSuperAdmin' => $isSuperAdmin,
        ]);
    }

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        abort_unless(
            $request->user()->hasRole('super_admin_root'),
            403,
        );

        $filters = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'role' => ['sometimes', 'nullable', 'string', 'exists:roles,slug'],
            'status' => ['sometimes', 'nullable', 'string'],
        ]);

        $validStatuses = array_map(
            static fn (UserStatus $status): string => $status->value,
            UserStatus::cases(),
        );

        if (isset($filters['status']) && $filters['status'] !== '') {
            abort_unless(in_array($filters['status'], $validStatuses, true), 422);
        }

        $users = User::query()
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

        return view('backoffice.users.index', [
            'users' => $users,
            'roles' => Role::query()->where('is_active', true)->orderBy('name')->get(['name', 'slug']),
            'statuses' => UserStatus::cases(),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'role' => $filters['role'] ?? '',
                'status' => $filters['status'] ?? '',
            ],
        ]);
    }
}
