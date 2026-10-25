<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Policies;

use App\Core\Foundation\Policies\BasePolicy;
use App\Domains\Catalog\Models\Catalog;
use App\Domains\Identity\Users\Models\User;

final class CatalogPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAuthorized()
            && $user->hasPermission('catalogs.view');
    }

    public function view(User $user, Catalog $catalog): bool
    {
        return $this->canAccessCatalog(
            $user,
            $catalog,
            'catalogs.view',
        );
    }

    public function create(
        User $user,
        string $cooperativeId,
    ): bool {
        return $this->canAccessCooperative(
            $user,
            $cooperativeId,
        ) && $user->hasPermission('catalogs.create');
    }

    public function update(User $user, Catalog $catalog): bool
    {
        return $this->canAccessCatalog(
            $user,
            $catalog,
            'catalogs.update',
        );
    }

    public function delete(User $user, Catalog $catalog): bool
    {
        return $this->canAccessCatalog(
            $user,
            $catalog,
            'catalogs.delete',
        );
    }

    public function restore(User $user, Catalog $catalog): bool
    {
        return $this->canAccessCatalog(
            $user,
            $catalog,
            'catalogs.restore',
        );
    }

    public function forceDelete(User $user, Catalog $catalog): bool
    {
        return $this->canAccessCatalog(
            $user,
            $catalog,
            'catalogs.force_delete',
        );
    }

    private function canAccessCatalog(
        User $user,
        Catalog $catalog,
        string $permission,
    ): bool {
        return $this->canAccessCooperative(
            $user,
            (string) $catalog->cooperative_id,
        ) && $user->hasPermission($permission);
    }

    private function canAccessCooperative(
        User $user,
        string $cooperativeId,
    ): bool {
        if (! $user->isAuthorized()) {
            return false;
        }

        if ($user->hasRole('super_admin_root')) {
            return true;
        }

        if ($user->hasRole('super_admin')) {
            return $user->cooperatives()
                ->whereKey($cooperativeId)
                ->exists();
        }

        if ($user->hasAnyRole([
            'cooperative_admin',
            'user',
        ])) {
            return $user->cooperative_id !== null
                && (string) $user->cooperative_id === $cooperativeId;
        }

        return false;
    }
}
