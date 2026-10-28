<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Identity\Authorization\Models\Permission;
use App\Domains\Identity\Authorization\Models\Role;
use Illuminate\Database\Seeder;
use RuntimeException;

final class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $matrix = config('authorization.role_permissions');

        if (! is_array($matrix)) {
            throw new RuntimeException(
                'The MAHLINE role permission matrix is not configured.',
            );
        }

        foreach ($matrix as $roleSlug => $permissionSlugs) {
            $role = Role::query()
                ->where('slug', $roleSlug)
                ->first();

            if ($role === null) {
                throw new RuntimeException(
                    sprintf(
                        'The role "%s" does not exist. Run RoleSeeder first.',
                        $roleSlug,
                    ),
                );
            }

            $permissionSlugs = array_values(
                array_unique($permissionSlugs),
            );

            $permissions = Permission::query()
                ->whereIn('slug', $permissionSlugs)
                ->where('is_active', true)
                ->get()
                ->keyBy('slug');

            $missingPermissions = array_diff(
                $permissionSlugs,
                $permissions->keys()->all(),
            );

            if ($missingPermissions !== []) {
                throw new RuntimeException(
                    sprintf(
                        'Missing or inactive permissions for role "%s": %s',
                        $roleSlug,
                        implode(', ', $missingPermissions),
                    ),
                );
            }

            /*
             * The matrix is authoritative:
             * permissions not declared for this role are detached.
             *
             * The role_permission table has no timestamps.
             */
            $role->permissions()->sync(
                $permissions
                    ->pluck('id')
                    ->all(),
            );
        }
    }
}