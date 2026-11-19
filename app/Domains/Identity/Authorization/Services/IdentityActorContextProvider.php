<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authorization\Services;

use App\Domains\Identity\Users\Models\User;
use App\Shared\Contracts\ActorContextProvider;
use App\Shared\DTOs\ActorContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class IdentityActorContextProvider implements ActorContextProvider
{
    public function forUserId(string $userId): ActorContext
    {
        $user = User::query()->with('roles.permissions')->find($userId);

        if ($user === null) {
            throw (new ModelNotFoundException)->setModel(User::class, [$userId]);
        }

        $roles = $user->roles
            ->filter(static fn ($role): bool => $role->is_active)
            ->pluck('slug')
            ->unique()
            ->values()
            ->all();

        $permissions = $user->roles
            ->filter(static fn ($role): bool => $role->is_active)
            ->flatMap(static fn ($role) => $role->permissions)
            ->filter(static fn ($permission): bool => $permission->is_active)
            ->pluck('slug')
            ->unique()
            ->values()
            ->all();

        return new ActorContext(
            id: (string) $user->getKey(),
            actorType: User::class,
            roles: $roles,
            permissions: $permissions,
            cooperativeId: $user->cooperative_id === null ? null : (string) $user->cooperative_id,
            authorized: $user->isAuthorized(),
        );
    }
}
