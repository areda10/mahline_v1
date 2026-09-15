<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authorization\Concerns;

use App\Domains\Identity\Authorization\Models\Permission;
use App\Domains\Identity\Authorization\Models\Role;

trait HasAuthorization
{
    public function hasRole(string|Role $role): bool
    {
        $slug = $role instanceof Role
            ? $role->slug
            : $role;

        return $this->roles()
            ->where('slug', $slug)
            ->exists();
    }

    public function hasAnyRole(array $roles): bool
    {
        return $this->roles()
            ->whereIn('slug', $this->normalizeSlugs($roles))
            ->exists();
    }

    public function hasPermission(string|Permission $permission): bool
    {
        $slug = $permission instanceof Permission
            ? $permission->slug
            : $permission;

        return $this->roles()
            ->whereHas('permissions', function ($query) use ($slug): void {
                $query->where('slug', $slug);
            })
            ->exists();
    }

    public function hasAnyPermission(array $permissions): bool
    {
        return $this->roles()
            ->whereHas('permissions', function ($query) use ($permissions): void {
                $query->whereIn('slug', $this->normalizeSlugs($permissions));
            })
            ->exists();
    }

    private function normalizeSlugs(array $items): array
    {
        return array_map(
            static fn (string|Role|Permission $item): string => $item instanceof Role || $item instanceof Permission
                ? $item->slug
                : $item,
            $items,
        );
    }
}