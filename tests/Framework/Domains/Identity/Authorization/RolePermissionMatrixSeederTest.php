<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Authorization;

use App\Domains\Identity\Authorization\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RolePermissionMatrixSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_permission_matrix_is_seeded(): void
    {
        $this->seed([
            \Database\Seeders\RoleSeeder::class,
            \Database\Seeders\PermissionSeeder::class,
            \Database\Seeders\RolePermissionSeeder::class,
        ]);

        $root = Role::query()
            ->where('slug', 'super_admin_root')
            ->firstOrFail();

        $this->assertTrue(
            $root->permissions()
                ->where('slug', 'products.force_delete')
                ->exists(),
        );

        $user = Role::query()
            ->where('slug', 'user')
            ->firstOrFail();

        $this->assertTrue(
            $user->permissions()
                ->where('slug', 'products.view')
                ->exists(),
        );

        $this->assertFalse(
            $user->permissions()
                ->where('slug', 'products.create')
                ->exists(),
        );
    }

    public function test_role_permission_matrix_is_idempotent(): void
    {
        $this->seed([
            \Database\Seeders\RoleSeeder::class,
            \Database\Seeders\PermissionSeeder::class,
            \Database\Seeders\RolePermissionSeeder::class,
        ]);

        $role = Role::query()
            ->where('slug', 'cooperative_admin')
            ->firstOrFail();

        $firstPermissionIds = $role->permissions()
            ->orderBy('permissions.id')
            ->pluck('permissions.id')
            ->all();

        $this->seed(
            \Database\Seeders\RolePermissionSeeder::class,
        );

        $role->refresh();

        $secondPermissionIds = $role->permissions()
            ->orderBy('permissions.id')
            ->pluck('permissions.id')
            ->all();

        $this->assertSame(
            $firstPermissionIds,
            $secondPermissionIds,
        );
    }
}