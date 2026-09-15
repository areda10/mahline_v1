<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Identity\Authorization;

use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UserRoleRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_roles_relationship(): void
    {
        $user = User::factory()->create();
        $role = Role::query()->create([
            'name' => 'Administrator',
            'slug' => 'administrator',
        ]);

        $user->roles()->attach($role);

        $this->assertTrue(
            $user->roles->contains($role)
        );
    }

    public function test_role_has_users_relationship(): void
    {
        $user = User::factory()->create();
        $role = Role::query()->create([
            'name' => 'Administrator',
            'slug' => 'administrator',
        ]);

        $role->users()->attach($user);

        $this->assertTrue(
            $role->users->contains($user)
        );
    }

    public function test_user_role_pivot_row_exists(): void
    {
        $user = User::factory()->create();
        $role = Role::query()->create([
            'name' => 'Administrator',
            'slug' => 'administrator',
        ]);

        $user->roles()->attach($role);

        $this->assertDatabaseHas('user_role', [
            'user_id' => $user->id,
            'role_id' => $role->id,
        ]);
    }

    public function test_user_can_detach_role(): void
    {
        $user = User::factory()->create();
        $role = Role::query()->create([
            'name' => 'Administrator',
            'slug' => 'administrator',
        ]);

        $user->roles()->attach($role);
        $user->roles()->detach($role);

        $this->assertDatabaseMissing('user_role', [
            'user_id' => $user->id,
            'role_id' => $role->id,
        ]);
    }
}