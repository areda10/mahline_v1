<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Authorization\Concerns;

use App\Domains\Identity\Authorization\Models\Permission;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UserAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_role(): void
    {
        $user = User::factory()->create();

        $role = Role::query()->create([
            'name' => 'Administrator',
            'slug' => 'administrator',
        ]);

        $user->roles()->attach($role);

        $this->assertTrue($user->hasRole('administrator'));
        $this->assertTrue($user->hasRole($role));
    }

    public function test_user_does_not_have_unassigned_role(): void
    {
        $user = User::factory()->create();

        Role::query()->create([
            'name' => 'Administrator',
            'slug' => 'administrator',
        ]);

        $this->assertFalse($user->hasRole('administrator'));
    }

    public function test_user_has_any_role(): void
    {
        $user = User::factory()->create();

        $role = Role::query()->create([
            'name' => 'Manager',
            'slug' => 'manager',
        ]);

        $user->roles()->attach($role);

        $this->assertTrue(
            $user->hasAnyRole([
                'administrator',
                'manager',
            ])
        );
    }

    public function test_user_has_permission_through_role(): void
    {
        $user = User::factory()->create();

        $role = Role::query()->create([
            'name' => 'Administrator',
            'slug' => 'administrator',
        ]);

        $permission = Permission::query()->create([
            'name' => 'Manage Users',
            'slug' => 'users.manage',
        ]);

        $role->permissions()->attach($permission);
        $user->roles()->attach($role);

        $this->assertTrue($user->hasPermission('users.manage'));
        $this->assertTrue($user->hasPermission($permission));
    }

    public function test_user_does_not_have_unassigned_permission(): void
    {
        $user = User::factory()->create();

        $permission = Permission::query()->create([
            'name' => 'Manage Users',
            'slug' => 'users.manage',
        ]);

        $this->assertFalse(
            $user->hasPermission('users.manage')
        );
    }

    public function test_user_has_any_permission(): void
    {
        $user = User::factory()->create();

        $role = Role::query()->create([
            'name' => 'Manager',
            'slug' => 'manager',
        ]);

        $permission = Permission::query()->create([
            'name' => 'Manage Products',
            'slug' => 'products.manage',
        ]);

        $role->permissions()->attach($permission);
        $user->roles()->attach($role);

        $this->assertTrue(
            $user->hasAnyPermission([
                'users.manage',
                'products.manage',
            ])
        );
    }

    public function test_user_can_have_permissions_from_multiple_roles(): void
    {
        $user = User::factory()->create();

        $adminRole = Role::query()->create([
            'name' => 'Administrator',
            'slug' => 'administrator',
        ]);

        $managerRole = Role::query()->create([
            'name' => 'Manager',
            'slug' => 'manager',
        ]);

        $usersPermission = Permission::query()->create([
            'name' => 'Manage Users',
            'slug' => 'users.manage',
        ]);

        $productsPermission = Permission::query()->create([
            'name' => 'Manage Products',
            'slug' => 'products.manage',
        ]);

        $adminRole->permissions()->attach($usersPermission);
        $managerRole->permissions()->attach($productsPermission);

        $user->roles()->attach([
            $adminRole->id,
            $managerRole->id,
        ]);

        $this->assertTrue($user->hasPermission('users.manage'));
        $this->assertTrue($user->hasPermission('products.manage'));
    }

    public function test_active_user_can_use_permission(): void
    {
        $user = User::factory()->create([
            'status' => UserStatus::Active,
        ]);

        $role = Role::query()->create([
            'name' => 'Administrator',
            'slug' => 'administrator',
        ]);

        $permission = Permission::query()->create([
            'name' => 'Manage Users',
            'slug' => 'users.manage',
        ]);

        $role->permissions()->attach($permission);
        $user->roles()->attach($role);

        $this->assertTrue(
            $user->hasPermission('users.manage')
        );
    }

    public function test_pending_user_cannot_use_permission(): void
    {
        $this->assertUnauthorizedStatus(UserStatus::Pending);
    }

    public function test_inactive_user_cannot_use_permission(): void
    {
        $this->assertUnauthorizedStatus(UserStatus::Inactive);
    }

    public function test_suspended_user_cannot_use_permission(): void
    {
        $this->assertUnauthorizedStatus(UserStatus::Suspended);
    }

    public function test_archived_user_cannot_use_permission(): void
    {
        $this->assertUnauthorizedStatus(UserStatus::Archived);
    }

    private function assertUnauthorizedStatus(UserStatus $status): void
    {
        $user = User::factory()->create([
            'status' => $status,
        ]);

        $role = Role::query()->create([
            'name' => 'Administrator',
            'slug' => 'administrator',
        ]);

        $permission = Permission::query()->create([
            'name' => 'Manage Users',
            'slug' => 'users.manage',
        ]);

        $role->permissions()->attach($permission);
        $user->roles()->attach($role);

        $this->assertFalse(
            $user->isAuthorized()
        );

        $this->assertFalse(
            $user->hasPermission('users.manage')
        );
    }
}