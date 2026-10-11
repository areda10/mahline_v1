<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authorization\Policies;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Users\Models\User;

final class CooperativePolicy
{
    private const ROLE_SUPER_ADMIN_ROOT = 'super_admin_root';

    private const ROLE_SUPER_ADMIN = 'super_admin';

    private const ROLE_COOPERATIVE_ADMIN = 'cooperative_admin';

    private const ROLE_USER = 'user';

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'cooperatives.view')
            && $this->hasSupportedRole($user);
    }

    public function view(User $user, Cooperative $cooperative): bool
    {
        return $this->canAccessCooperative($user, $cooperative)
            && $this->hasPermission($user, 'cooperatives.view');
    }

    public function create(User $user): bool
    {
        return $user->hasRole(self::ROLE_SUPER_ADMIN_ROOT)
            && $this->hasPermission($user, 'cooperatives.create');
    }

    public function update(User $user, Cooperative $cooperative): bool
    {
        return $this->canAccessCooperative($user, $cooperative)
            && $this->hasPermission($user, 'cooperatives.update');
    }

    public function delete(User $user, Cooperative $cooperative): bool
    {
        return $this->canAccessCooperative($user, $cooperative)
            && $this->hasPermission($user, 'cooperatives.delete');
    }

    private function canAccessCooperative(
        User $user,
        Cooperative $cooperative,
    ): bool {
        if (! $user->isAuthorized()) {
            return false;
        }

        if ($user->hasRole(self::ROLE_SUPER_ADMIN_ROOT)) {
            return true;
        }

        if ($user->hasRole(self::ROLE_SUPER_ADMIN)) {
            return $user->cooperatives()
                ->whereKey($cooperative->getKey())
                ->exists();
        }

        if (
            $user->hasAnyRole([
                self::ROLE_COOPERATIVE_ADMIN,
                self::ROLE_USER,
            ])
        ) {
            return $user->cooperative_id !== null
                && (string) $user->cooperative_id
                    === (string) $cooperative->getKey();
        }

        return false;
    }

    private function hasPermission(
        User $user,
        string $permission,
    ): bool {
        return $user->isAuthorized()
            && $user->hasPermission($permission);
    }

    private function hasSupportedRole(User $user): bool
    {
        return $user->hasAnyRole([
            self::ROLE_SUPER_ADMIN_ROOT,
            self::ROLE_SUPER_ADMIN,
            self::ROLE_COOPERATIVE_ADMIN,
            self::ROLE_USER,
        ]);
    }
}