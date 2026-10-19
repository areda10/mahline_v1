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
        return $user->hasPermission('categories.view');
    }

    public function view(
        User $user,
        Category $category,
    ): bool {
        return $user->hasPermission('categories.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('categories.create');
    }

    public function update(
        User $user,
        Category $category,
    ): bool {
        return $user->hasPermission('categories.update');
    }

    public function delete(
        User $user,
        Category $category,
    ): bool {
        return $user->hasPermission('categories.delete');
    }

    public function restore(
        User $user,
        Category $category,
    ): bool {
        return $user->hasPermission('categories.restore');
    }

    public function forceDelete(
        User $user,
        Category $category,
    ): bool {
        return $user->hasPermission('categories.force_delete');
    }
}