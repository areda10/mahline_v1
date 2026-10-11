<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authorization\Concerns;

use App\Domains\Identity\Authorization\Models\Permission;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Enums\UserStatus;

trait HasAuthorization
{
    public function hasRole(string|Role $role): bool
    {
        if (! $this->isAuthorized()) {
            return false;
        }

        $slug = $role instanceof Role
            ? $role->slug
            : $role;

        return $this->roles()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->exists();
    }

    public function hasAnyRole(array $roles): bool
    {
        if (! $this->isAuthorized()) {
            return false;
        }

        return $this->roles()
            ->whereIn('slug', $this->normalizeSlugs($roles))
            ->where('is_active', true)
            ->exists();
    }

    public function hasPermission(string|Permission $permission): bool
    {
        if (! $this->isAuthorized()) {
            return false;
        }

        $slug = $permission instanceof Permission
            ? $permission->slug
            : $permission;

        return $this->roles()
            ->where('is_active', true)
            ->whereHas('permissions', function ($query) use ($slug): void {
                $query
                    ->where('slug', $slug)
                    ->where('is_active', true);
            })
            ->exists();
    }

    public function hasAnyPermission(array $permissions): bool
    {
        if (! $this->isAuthorized()) {
            return false;
        }

        return $this->roles()
            ->where('is_active', true)
            ->whereHas('permissions', function ($query) use ($permissions): void {
                $query
                    ->whereIn('slug', $this->normalizeSlugs($permissions))
                    ->where('is_active', true);
            })
            ->exists();
    }

    private function normalizeSlugs(array $items): array
    {
        return array_map(
            static fn (string|Role|Permission $item): string =>
                $item instanceof Role || $item instanceof Permission
                    ? $item->slug
                    : $item,
            $items,
        );
    }

    public function isAuthorized(): bool
    {
        return $this->status === UserStatus::Active;
    }
}
