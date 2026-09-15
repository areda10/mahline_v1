<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Identity\Authorization;

use App\Domains\Identity\Authorization\Models\Permission;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

final class AuthorizationGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_gate_allows_user_with_permission(): void
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

        $this->assertTrue(
            Gate::forUser($user)->allows('users.manage')
        );
    }

    public function test_gate_denies_user_without_permission(): void
    {
        $user = User::factory()->create();

        $this->assertFalse(
            Gate::forUser($user)->allows('users.manage')
        );
    }

    public function test_gate_allows_permission_inherited_from_role(): void
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
            Gate::forUser($user)->allows('products.manage')
        );
    }

    public function test_gate_denies_unknown_ability(): void
    {
        $user = User::factory()->create();

        $this->assertFalse(
            Gate::forUser($user)->allows('unknown.permission')
        );
    }
}