<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Identity\Authorization;

use App\Domains\Identity\Authorization\Models\Permission;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UserAuthorizationStatusTest extends TestCase
{
    use RefreshDatabase;

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