<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\UserManagement\Rules;

use App\Domains\Identity\Authorization\Models\Role;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UserManagementPermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
        ]);
    }

    public function test_super_admin_root_has_all_user_lifecycle_and_assignment_permissions(): void
    {
        $permissions = $this->permissionSlugs('super_admin_root');

        foreach ([
            'users.view',
            'users.create',
            'users.activate',
            'users.deactivate',
            'users.suspend',
            'users.archive',
            'users.restore',
            'users.delete',
            'users.restore_deleted',
            'users.assign_super_admin',
            'users.unassign_super_admin',
        ] as $permission) {
            self::assertContains($permission, $permissions);
        }
    }

    public function test_super_admin_can_manage_basic_statuses_but_not_archive_delete_or_assign_super_admins(): void
    {
        $permissions = $this->permissionSlugs('super_admin');

        foreach ([
            'users.view',
            'users.create',
            'users.activate',
            'users.deactivate',
            'users.suspend',
        ] as $permission) {
            self::assertContains($permission, $permissions);
        }

        foreach ([
            'users.archive',
            'users.restore',
            'users.delete',
            'users.restore_deleted',
            'users.assign_super_admin',
            'users.unassign_super_admin',
        ] as $permission) {
            self::assertNotContains($permission, $permissions);
        }
    }

    public function test_cooperative_admin_can_only_view_users_and_cannot_assign_super_admins(): void
    {
        $permissions = $this->permissionSlugs('cooperative_admin');

        self::assertContains('users.view', $permissions);
        foreach ([
            'users.create',
            'users.activate',
            'users.deactivate',
            'users.suspend',
            'users.archive',
            'users.restore',
            'users.delete',
            'users.restore_deleted',
            'users.assign_super_admin',
            'users.unassign_super_admin',
        ] as $permission) {
            self::assertNotContains($permission, $permissions);
        }
    }

    public function test_standard_user_has_no_user_management_permissions(): void
    {
        $permissions = $this->permissionSlugs('user');

        foreach ([
            'users.view',
            'users.create',
            'users.activate',
            'users.deactivate',
            'users.suspend',
            'users.archive',
            'users.restore',
            'users.delete',
            'users.restore_deleted',
            'users.assign_super_admin',
            'users.unassign_super_admin',
        ] as $permission) {
            self::assertNotContains($permission, $permissions);
        }
    }

    /**
     * @return array<int, string>
     */
    private function permissionSlugs(string $roleSlug): array
    {
        $role = Role::query()
            ->where('slug', $roleSlug)
            ->firstOrFail();

        return $role->permissions()
            ->pluck('slug')
            ->all();
    }
}
