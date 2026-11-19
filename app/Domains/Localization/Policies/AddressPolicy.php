<?php

declare(strict_types=1);

namespace App\Domains\Localization\Policies;

use App\Core\Foundation\Policies\BasePolicy;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Users\Models\User;

final class AddressPolicy extends BasePolicy
{
    private const ROLE_SUPER_ADMIN_ROOT = 'super_admin_root';

    private const ROLE_SUPER_ADMIN = 'super_admin';

    private const ROLE_COOPERATIVE_ADMIN = 'cooperative_admin';

    private const ROLE_USER = 'user';

    private const ROLE_CUSTOMER = 'customer';

    /**
     * Determine whether an actor may update a user's personal address.
     *
     * Every active, verified supported account may update its own address.
     * Updating another user's address additionally requires users.update
     * and an administrative role with access to the target's cooperative.
     */
    public function updateUserAddress(User $actor, User $target): bool
    {
        if (! $actor->isAuthorized()) {
            return false;
        }

        if ((string) $actor->getKey() === (string) $target->getKey()) {
            return $actor->hasAnyRole([
                self::ROLE_SUPER_ADMIN_ROOT,
                self::ROLE_SUPER_ADMIN,
                self::ROLE_COOPERATIVE_ADMIN,
                self::ROLE_USER,
                self::ROLE_CUSTOMER,
            ]);
        }

        if (! $actor->hasPermission('users.update')) {
            return false;
        }

        if ($actor->hasRole(self::ROLE_SUPER_ADMIN_ROOT)) {
            return ! $target->hasAnyRole(['super_admin_root', 'customer']);
        }

        if ($target->cooperative_id === null) {
            return false;
        }

        if ($actor->hasRole(self::ROLE_SUPER_ADMIN)) {
            return $actor->cooperatives()
                ->whereKey($target->cooperative_id)
                ->exists();
        }

        if ($actor->hasRole(self::ROLE_COOPERATIVE_ADMIN)) {
            return $target->hasRole(self::ROLE_USER)
                && $actor->cooperative_id !== null
                && (string) $actor->cooperative_id === (string) $target->cooperative_id;
        }

        return false;
    }

    /**
     * Determine whether an actor may create the address as part of cooperative creation.
     */
    public function createCooperativeAddress(User $actor, Cooperative $cooperative): bool
    {
        if (! $actor->isAuthorized() || ! $actor->hasPermission('cooperatives.create')) {
            return false;
        }

        if ($actor->hasRole(self::ROLE_SUPER_ADMIN_ROOT)) {
            return true;
        }

        return $actor->hasRole(self::ROLE_SUPER_ADMIN)
            && $actor->cooperatives()->whereKey($cooperative->getKey())->exists();
    }

    /**
     * Determine whether an actor may update a cooperative's address.
     */
    public function updateCooperativeAddress(User $actor, Cooperative $cooperative): bool
    {
        if (! $actor->isAuthorized() || ! $actor->hasPermission('cooperatives.update')) {
            return false;
        }

        if ($actor->hasRole(self::ROLE_SUPER_ADMIN_ROOT)) {
            return true;
        }

        if ($actor->hasRole(self::ROLE_SUPER_ADMIN)) {
            return $actor->cooperatives()
                ->whereKey($cooperative->getKey())
                ->exists();
        }

        if ($actor->hasRole(self::ROLE_COOPERATIVE_ADMIN)) {
            return $actor->cooperative_id !== null
                && (string) $actor->cooperative_id === (string) $cooperative->getKey();
        }

        return false;
    }
}
