<?php

declare(strict_types=1);

namespace App\Domains\Categories\Policies;

use App\Core\Foundation\Policies\BasePolicy;
use App\Domains\Categories\Models\Category;
use App\Domains\Identity\Users\Models\User;

final class CategoryPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAuthorized()
            && $user->hasPermission('categories.view');
    }

    public function view(
        User $user,
        Category $category,
    ): bool {
        if (! $user->isAuthorized()) {
            return false;
        }

        // Les catégories globales sont consultables avec la permission.
        if ($category->isGlobal()) {
            return $user->hasPermission('categories.view');
        }

        return $this->canAccessCooperative(
            $user,
            (string) $category->cooperative_id,
        ) && $user->hasPermission('categories.view');
    }

    /**
     * Autorisation générale de création.
     *
     * Le contrôleur doit déterminer le périmètre de création :
     * global ou coopérative. Le périmètre ne doit jamais être
     * accepté aveuglément depuis les données du formulaire.
     */
    public function create(User $user): bool
    {
        return $user->isAuthorized()
            && $user->hasPermission('categories.create');
    }

    public function update(
        User $user,
        Category $category,
    ): bool {
        return $this->canManageCategory(
            $user,
            $category,
            'categories.update',
        );
    }

    public function delete(
        User $user,
        Category $category,
    ): bool {
        return $this->canManageCategory(
            $user,
            $category,
            'categories.delete',
        );
    }

    public function restore(
        User $user,
        Category $category,
    ): bool {
        return $this->canManageCategory(
            $user,
            $category,
            'categories.restore',
        );
    }

    public function forceDelete(
        User $user,
        Category $category,
    ): bool {
        return $this->canManageCategory(
            $user,
            $category,
            'categories.force_delete',
        );
    }

    private function canManageCategory(
        User $user,
        Category $category,
        string $permission,
    ): bool {
        if (! $user->isAuthorized()) {
            return false;
        }

        // Une catégorie globale est administrée uniquement
        // par le super_admin_root.
        if ($category->isGlobal()) {
            return $user->hasRole('super_admin_root')
                && $user->hasPermission($permission);
        }

        return $this->canAccessCooperative(
            $user,
            (string) $category->cooperative_id,
        ) && $user->hasPermission($permission);
    }

    private function canAccessCooperative(
        User $user,
        string $cooperativeId,
    ): bool {
        if (! $user->isAuthorized()) {
            return false;
        }

        // Administration globale de MAHLINE.
        if ($user->hasRole('super_admin_root')) {
            return true;
        }

        // Un super-admin ne peut agir que sur ses coopératives
        // assignées.
        if ($user->hasRole('super_admin')) {
            return $user->cooperatives()
                ->whereKey($cooperativeId)
                ->exists();
        }

        // L'admin et l'utilisateur d'une coopérative restent
        // limités à leur propre coopérative.
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
