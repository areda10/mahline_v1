<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Authorization\Concerns;

use App\Domains\Identity\Authorization\Models\Permission;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HasAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_role(): void
    {
        $user = User::factory()->create([
            'status' => UserStatus::Active,
        ]);

        $role = Role::create([
            'name' => 'Administrator',
            'slug' => 'administrator',
            'is_active' => true,
        ]);

        $user->roles()->attach($role);

        $this->assertTrue(
            $user->hasRole('administrator'),
        );
    }

    public function test_user_does_not_have_unassigned_role(): void
    {
        $user = User::factory()->create([
            'status' => UserStatus::Active,
        ]);

        Role::create([
            'name' => 'Administrator',
            'slug' => 'administrator',
            'is_active' => true,
        ]);

        $this->assertFalse(
            $user->hasRole('administrator'),
        );
    }

    public function test_user_has_any_role(): void
    {
        $user = User::factory()->create([
            'status' => UserStatus::Active,
        ]);

        $role = Role::create([
            'name' => 'Manager',
            'slug' => 'manager',
            'is_active' => true,
        ]);

        $user->roles()->attach($role);

        $this->assertTrue(
            $user->hasAnyRole([
                'administrator',
                'manager',
            ]),
        );
    }

    public function test_user_has_permission_through_role(): void
    {
        $user = User::factory()->create([
            'status' => UserStatus::Active,
        ]);

        $role = Role::create([
            'name' => 'Manager',
            'slug' => 'manager',
            'is_active' => true,
        ]);

        $permission = Permission::create([
            'name' => 'View products',
            'slug' => 'products.view',
            'is_active' => true,
        ]);

        $role->permissions()->attach($permission);
        $user->roles()->attach($role);

        $this->assertTrue(
            $user->hasPermission('products.view'),
        );
    }

    public function test_user_does_not_have_unassigned_permission(): void
    {
        $user = User::factory()->create([
            'status' => UserStatus::Active,
        ]);

        Role::create([
            'name' => 'Manager',
            'slug' => 'manager',
            'is_active' => true,
        ]);

        Permission::create([
            'name' => 'View products',
            'slug' => 'products.view',
            'is_active' => true,
        ]);

        $this->assertFalse(
            $user->hasPermission('products.view'),
        );
    }

    public function test_user_has_any_permission(): void
    {
        $user = User::factory()->create([
            'status' => UserStatus::Active,
        ]);

        $role = Role::create([
            'name' => 'Manager',
            'slug' => 'manager',
            'is_active' => true,
        ]);

        $permission = Permission::create([
            'name' => 'View products',
            'slug' => 'products.view',
            'is_active' => true,
        ]);

        $role->permissions()->attach($permission);
        $user->roles()->attach($role);

        $this->assertTrue(
            $user->hasAnyPermission([
                'products.create',
                'products.view',
            ]),
        );
    }

    public function test_user_can_have_permissions_from_multiple_roles(): void
    {
        $user = User::factory()->create([
            'status' => UserStatus::Active,
        ]);

        $manager = Role::create([
            'name' => 'Manager',
            'slug' => 'manager',
            'is_active' => true,
        ]);

        $editor = Role::create([
            'name' => 'Editor',
            'slug' => 'editor',
            'is_active' => true,
        ]);

        $viewPermission = Permission::create([
            'name' => 'View products',
            'slug' => 'products.view',
            'is_active' => true,
        ]);

        $updatePermission = Permission::create([
            'name' => 'Update products',
            'slug' => 'products.update',
            'is_active' => true,
        ]);

        $manager->permissions()->attach($viewPermission);
        $editor->permissions()->attach($updatePermission);

        $user->roles()->attach([
            $manager->getKey(),
            $editor->getKey(),
        ]);

        $this->assertTrue(
            $user->hasPermission('products.view'),
        );

        $this->assertTrue(
            $user->hasPermission('products.update'),
        );
    }

    public function test_active_user_can_use_permission(): void
    {
        $user = $this->createUserWithPermission(
            UserStatus::Active,
        );

        $this->assertTrue(
            $user->hasPermission('products.view'),
        );
    }

    public function test_pending_user_cannot_use_permission(): void
    {
        $this->assertUnauthorizedStatus(
            UserStatus::Pending,
        );
    }

    public function test_inactive_user_cannot_use_permission(): void
    {
        $this->assertUnauthorizedStatus(
            UserStatus::Inactive,
        );
    }

    public function test_suspended_user_cannot_use_permission(): void
    {
        $this->assertUnauthorizedStatus(
            UserStatus::Suspended,
        );
    }

    public function test_archived_user_cannot_use_permission(): void
    {
        $this->assertUnauthorizedStatus(
            UserStatus::Archived,
        );
    }

    private function createUserWithPermission(
        UserStatus $status,
    ): User {
        $user = User::factory()->create([
            'status' => $status,
        ]);

        $role = Role::create([
            'name' => 'Manager',
            'slug' => 'manager',
            'is_active' => true,
        ]);

        $permission = Permission::create([
            'name' => 'View products',
            'slug' => 'products.view',
            'is_active' => true,
        ]);

        $role->permissions()->attach($permission);
        $user->roles()->attach($role);

        return $user;
    }

    private function assertUnauthorizedStatus(
        UserStatus $status,
    ): void {
        $user = $this->createUserWithPermission($status);

        $this->assertFalse(
            $user->isAuthorized(),
        );

        $this->assertFalse(
            $user->hasPermission('products.view'),
        );
    }

public function test_inactive_role_does_not_grant_permission(): void
{
    $user = $this->createUserWithPermission(
        UserStatus::Active,
    );

    $user->roles()->first()->update([
        'is_active' => false,
    ]);

    $this->assertFalse(
        $user->hasPermission('products.view'),
    );
}

public function test_inactive_permission_is_not_granted(): void
{
    $user = $this->createUserWithPermission(
        UserStatus::Active,
    );

    Permission::query()
        ->where('slug', 'products.view')
        ->update(['is_active' => false]);

    $this->assertFalse(
        $user->hasPermission('products.view'),
    );
}

public function test_deleted_role_does_not_grant_permission(): void
{
    $user = $this->createUserWithPermission(
        UserStatus::Active,
    );

    $user->roles()->first()->delete();

    $this->assertFalse(
        $user->hasPermission('products.view'),
    );
}

public function test_deleted_permission_is_not_granted(): void
{
    $user = $this->createUserWithPermission(
        UserStatus::Active,
    );

    Permission::query()
        ->where('slug', 'products.view')
        ->firstOrFail()
        ->delete();

    $this->assertFalse(
        $user->hasPermission('products.view'),
    );
}

public function test_has_role_accepts_role_model(): void
{
    $user = User::factory()->create([
        'status' => UserStatus::Active,
    ]);

    $role = Role::create([
        'name' => 'Manager',
        'slug' => 'manager',
        'is_active' => true,
    ]);

    $user->roles()->attach($role);

    $this->assertTrue(
        $user->hasRole($role),
    );
}

public function test_has_permission_accepts_permission_model(): void
{
    $user = $this->createUserWithPermission(
        UserStatus::Active,
    );

    $permission = Permission::query()
        ->where('slug', 'products.view')
        ->firstOrFail();

    $this->assertTrue(
        $user->hasPermission($permission),
    );
}

public function test_has_any_role_returns_false_for_empty_array(): void
{
    $user = User::factory()->create([
        'status' => UserStatus::Active,
    ]);

    $this->assertFalse(
        $user->hasAnyRole([]),
    );
}

public function test_has_any_permission_returns_false_for_empty_array(): void
{
    $user = User::factory()->create([
        'status' => UserStatus::Active,
    ]);

    $this->assertFalse(
        $user->hasAnyPermission([]),
    );
}

}
