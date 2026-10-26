<?php

declare(strict_types=1);

namespace App\Domains\Products\Policies;

use App\Domains\Products\Models\Product;
use App\Domains\Identity\Users\Models\User;

final class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAuthorized()
            && $user->hasPermission('products.view');
    }

    public function view(User $user, Product $product): bool
    {
        return $this->canAccessProduct(
            $user,
            $product,
            'products.view',
        );
    }

    public function create(
        User $user,
        string $cooperativeId,
    ): bool {
        return $this->canAccessCooperative(
            $user,
            $cooperativeId,
        ) && $user->hasPermission('products.create');
    }

    public function update(User $user, Product $product): bool
    {
        return $this->canAccessProduct(
            $user,
            $product,
            'products.update',
        );
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->canAccessProduct(
            $user,
            $product,
            'products.delete',
        );
    }

    public function restore(User $user, Product $product): bool
    {
        return $this->canAccessProduct(
            $user,
            $product,
            'products.restore',
        );
    }

    public function forceDelete(
        User $user,
        Product $product,
    ): bool {
        return $this->canAccessProduct(
            $user,
            $product,
            'products.force_delete',
        );
    }

    private function canAccessProduct(
        User $user,
        Product $product,
        string $permission,
    ): bool {
        return $this->canAccessCooperative(
            $user,
            (string) $product->cooperative_id,
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

        if (
            $user->hasAnyRole([
                'cooperative_admin',
                'user',
            ])
        ) {
            return $user->cooperative_id !== null
                && (string) $user->cooperative_id === $cooperativeId;
        }

        return false;
    }


    public function reactivate(User $user, Product $product): bool
    {
        return $this->canAccessProduct(
            $user,
            $product,
            'products.update',
        );
    }


}