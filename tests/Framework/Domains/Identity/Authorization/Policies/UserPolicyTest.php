<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Authorization\Policies;

use App\Domains\Identity\Authorization\Models\Permission;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

final class UserPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_can_view_users_across_cooperatives(): void
    {
        $root = $this->userWithRoleAndPermissions(
            'super_admin_root',
            ['users.view'],
        );

        $target = User::factory()->create([
            'cooperative_id' => (string) \Illuminate\Support\Str::ulid(),
        ]);

        self::assertTrue(Gate::forUser($root)->allows('view', $target));
    }

    public function test_super_admin_can_view_only_users_in_assigned_cooperatives(): void
    {
        $cooperativeId = (string) \Illuminate\Support\Str::ulid();
        $otherCooperativeId = (string) \Illuminate\Support\Str::ulid();

        $admin = $this->userWithRoleAndPermissions(
            'super_admin',
            ['users.view'],
        );
        $admin->cooperatives()->attach($cooperativeId);

        $assignedUser = User::factory()->create([
            'cooperative_id' => $cooperativeId,
        ]);
        $unassignedUser = User::factory()->create([
            'cooperative_id' => $otherCooperativeId,
        ]);

        self::assertTrue(Gate::forUser($admin)->allows('view', $assignedUser));
        self::assertFalse(Gate::forUser($admin)->allows('view', $unassignedUser));
    }

    public function test_cooperative_admin_can_view_only_users_in_own_cooperative(): void
    {
        $cooperativeId = (string) \Illuminate\Support\Str::ulid();

        $admin = $this->userWithRoleAndPermissions(
            'cooperative_admin',
            ['users.view'],
            ['cooperative_id' => $cooperativeId],
        );
        $ownUser = User::factory()->create(['cooperative_id' => $cooperativeId]);
        $otherUser = User::factory()->create([
            'cooperative_id' => (string) \Illuminate\Support\Str::ulid(),
        ]);

        self::assertTrue(Gate::forUser($admin)->allows('view', $ownUser));
        self::assertFalse(Gate::forUser($admin)->allows('view', $otherUser));
    }

    public function test_root_can_change_own_status(): void
    {
        $root = $this->userWithRoleAndPermissions(
            'super_admin_root',
            ['users.deactivate'],
        );

        self::assertTrue(Gate::forUser($root)->allows('deactivate', $root));
    }

    public function test_super_admin_cannot_change_own_status(): void
    {
        $admin = $this->userWithRoleAndPermissions(
            'super_admin',
            ['users.deactivate'],
        );

        self::assertFalse(Gate::forUser($admin)->allows('deactivate', $admin));
    }

    public function test_super_admin_cannot_reactivate_a_suspended_user(): void
    {
        $cooperativeId = (string) \Illuminate\Support\Str::ulid();
        $admin = $this->userWithRoleAndPermissions(
            'super_admin',
            ['users.activate'],
        );
        $admin->cooperatives()->attach($cooperativeId);

        $suspended = User::factory()->create([
            'cooperative_id' => $cooperativeId,
            'status' => UserStatus::Suspended,
        ]);

        self::assertFalse(Gate::forUser($admin)->allows('activate', $suspended));
    }

    public function test_cooperative_admin_cannot_change_user_status(): void
    {
        $cooperativeId = (string) \Illuminate\Support\Str::ulid();
        $admin = $this->userWithRoleAndPermissions(
            'cooperative_admin',
            ['users.activate', 'users.deactivate', 'users.suspend'],
            ['cooperative_id' => $cooperativeId],
        );
        $target = User::factory()->create([
            'cooperative_id' => $cooperativeId,
            'status' => UserStatus::Pending,
        ]);

        self::assertFalse(Gate::forUser($admin)->allows('activate', $target));
        self::assertFalse(Gate::forUser($admin)->allows('deactivate', $target));
        self::assertFalse(Gate::forUser($admin)->allows('suspend', $target));
    }

    public function test_only_root_can_archive_and_soft_delete_users(): void
    {
        $root = $this->userWithRoleAndPermissions(
            'super_admin_root',
            ['users.archive', 'users.delete'],
        );
        $admin = $this->userWithRoleAndPermissions(
            'super_admin',
            ['users.archive', 'users.delete'],
        );
        $target = User::factory()->create();

        self::assertTrue(Gate::forUser($root)->allows('archive', $target));
        self::assertTrue(Gate::forUser($root)->allows('delete', $target));
        self::assertFalse(Gate::forUser($admin)->allows('archive', $target));
        self::assertFalse(Gate::forUser($admin)->allows('delete', $target));
    }

    /**
     * @param list<string> $permissionSlugs
     * @param array<string, mixed> $attributes
     */
    private function userWithRoleAndPermissions(
        string $roleSlug,
        array $permissionSlugs,
        array $attributes = [],
    ): User {
        $user = User::factory()->create($attributes);

        $role = Role::query()->firstOrCreate(
            ['slug' => $roleSlug],
            ['name' => $roleSlug, 'is_active' => true],
        );

        foreach ($permissionSlugs as $slug) {
            $permission = Permission::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $slug, 'description' => $slug, 'is_active' => true],
            );
            $role->permissions()->syncWithoutDetaching([$permission->getKey()]);
        }

        $user->roles()->syncWithoutDetaching([$role->getKey()]);

        return $user;
    }
}
