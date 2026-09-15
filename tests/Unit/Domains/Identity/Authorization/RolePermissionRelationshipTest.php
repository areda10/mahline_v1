<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Identity\Authorization;

use App\Domains\Identity\Authorization\Models\Permission;
use App\Domains\Identity\Authorization\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RolePermissionRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_can_have_permissions(): void
    {
        $role = Role::create([
            'name' => 'Administrator',
            'slug' => 'administrator',
            'is_active' => true,
        ]);

        $permission = Permission::create([
            'name' => 'Create Products',
            'slug' => 'create-products',
            'is_active' => true,
        ]);

        $role->permissions()->attach($permission);

        $this->assertTrue(
            $role->permissions()
                ->whereKey($permission->getKey())
                ->exists()
        );
    }

    public function test_permission_can_have_roles(): void
    {
        $role = Role::create([
            'name' => 'Administrator',
            'slug' => 'administrator',
            'is_active' => true,
        ]);

        $permission = Permission::create([
            'name' => 'Create Products',
            'slug' => 'create-products',
            'is_active' => true,
        ]);

        $role->permissions()->attach($permission);

        $this->assertTrue(
            $permission->roles()
                ->whereKey($role->getKey())
                ->exists()
        );
    }

    public function test_role_and_permission_are_linked_through_pivot(): void
    {
        $role = Role::create([
            'name' => 'Administrator',
            'slug' => 'administrator',
            'is_active' => true,
        ]);

        $permission = Permission::create([
            'name' => 'Create Products',
            'slug' => 'create-products',
            'is_active' => true,
        ]);

        $role->permissions()->attach($permission);

        $this->assertDatabaseHas('role_permission', [
            'role_id' => $role->getKey(),
            'permission_id' => $permission->getKey(),
        ]);
    }

    public function test_role_can_detach_permission(): void
    {
        $role = Role::create([
            'name' => 'Administrator',
            'slug' => 'administrator',
            'is_active' => true,
        ]);

        $permission = Permission::create([
            'name' => 'Create Products',
            'slug' => 'create-products',
            'is_active' => true,
        ]);

        $role->permissions()->attach($permission);
        $role->permissions()->detach($permission);

        $this->assertDatabaseMissing('role_permission', [
            'role_id' => $role->getKey(),
            'permission_id' => $permission->getKey(),
        ]);
    }
}