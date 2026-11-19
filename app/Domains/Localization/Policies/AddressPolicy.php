<?php

declare(strict_types=1);

namespace App\Domains\Localization\Policies;

use App\Domains\Localization\DTOs\CooperativeAddressOwnerData;
use App\Domains\Localization\DTOs\UserAddressOwnerData;
use App\Shared\Contracts\SuperAdminCooperativeAssignmentReader;
use App\Shared\DTOs\ActorContext;

final class AddressPolicy
{
    private const ROLE_SUPER_ADMIN_ROOT = 'super_admin_root';

    private const ROLE_SUPER_ADMIN = 'super_admin';

    private const ROLE_COOPERATIVE_ADMIN = 'cooperative_admin';

    private const ROLE_USER = 'user';

    private const ROLE_CUSTOMER = 'customer';

    public function __construct(private readonly SuperAdminCooperativeAssignmentReader $assignments) {}

    /**
     * Every active supported account may update its own personal address.
     * Updating another account additionally requires users.update and a permitted perimeter.
     */
    public function updateUserAddress(ActorContext $actor, UserAddressOwnerData $target): bool
    {
        if (! $actor->authorized) {
            return false;
        }

        if ($actor->id === $target->id) {
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
            return ! $target->hasAnyRole([self::ROLE_SUPER_ADMIN_ROOT, self::ROLE_CUSTOMER]);
        }

        if ($target->cooperativeId === null) {
            return false;
        }

        if ($actor->hasRole(self::ROLE_SUPER_ADMIN)) {
            return $this->assignments->isAssigned($actor->id, $target->cooperativeId);
        }

        if ($actor->hasRole(self::ROLE_COOPERATIVE_ADMIN)) {
            return $target->hasRole(self::ROLE_USER)
                && $actor->cooperativeId !== null
                && $actor->cooperativeId === $target->cooperativeId;
        }

        return false;
    }

    public function createCooperativeAddress(
        ActorContext $actor,
        CooperativeAddressOwnerData $cooperative,
    ): bool {
        if (! $actor->authorized || ! $actor->hasPermission('cooperatives.create')) {
            return false;
        }

        if ($actor->hasRole(self::ROLE_SUPER_ADMIN_ROOT)) {
            return true;
        }

        return $actor->hasRole(self::ROLE_SUPER_ADMIN)
            && $this->assignments->isAssigned($actor->id, $cooperative->id);
    }

    public function updateCooperativeAddress(
        ActorContext $actor,
        CooperativeAddressOwnerData $cooperative,
    ): bool {
        if (! $actor->authorized || ! $actor->hasPermission('cooperatives.update')) {
            return false;
        }

        if ($actor->hasRole(self::ROLE_SUPER_ADMIN_ROOT)) {
            return true;
        }

        if ($actor->hasRole(self::ROLE_SUPER_ADMIN)) {
            return $this->assignments->isAssigned($actor->id, $cooperative->id);
        }

        return $actor->hasRole(self::ROLE_COOPERATIVE_ADMIN)
            && $actor->cooperativeId !== null
            && $actor->cooperativeId === $cooperative->id;
    }
}
