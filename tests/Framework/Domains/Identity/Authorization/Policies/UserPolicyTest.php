<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Authorization\Policies;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Countries\Models\Country;
use App\Domains\Identity\Authorization\Models\Permission;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Localization\Models\Address;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

final class UserPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_can_view_users_across_cooperatives(): void
    {
        $root = $this->userWithRoleAndPermissions('super_admin_root', ['users.view']);
        $target = User::factory()->create();

        self::assertTrue(Gate::forUser($root)->allows('view', $target));
    }

    public function test_super_admin_can_view_only_users_in_assigned_cooperatives(): void
    {
        $assignedCooperative = $this->createCooperative();
        $otherCooperative = $this->createCooperative();

        $admin = $this->userWithRoleAndPermissions('super_admin', ['users.view']);
        $admin->cooperatives()->attach($assignedCooperative->getKey());

        $assignedUser = User::factory()->create([
            'cooperative_id' => $assignedCooperative->getKey(),
        ]);
        $unassignedUser = User::factory()->create([
            'cooperative_id' => $otherCooperative->getKey(),
        ]);

        self::assertTrue(Gate::forUser($admin)->allows('view', $assignedUser));
        self::assertFalse(Gate::forUser($admin)->allows('view', $unassignedUser));
    }

    public function test_cooperative_admin_can_view_only_users_in_own_cooperative(): void
    {
        $cooperative = $this->createCooperative();
        $otherCooperative = $this->createCooperative();

        $admin = $this->userWithRoleAndPermissions(
            'cooperative_admin',
            ['users.view'],
            ['cooperative_id' => $cooperative->getKey()],
        );
        $ownUser = User::factory()->create([
            'cooperative_id' => $cooperative->getKey(),
        ]);
        $otherUser = User::factory()->create([
            'cooperative_id' => $otherCooperative->getKey(),
        ]);

        self::assertTrue(Gate::forUser($admin)->allows('view', $ownUser));
        self::assertFalse(Gate::forUser($admin)->allows('view', $otherUser));
    }

    public function test_cooperative_admin_cannot_create_users(): void
    {
        $admin = $this->userWithRoleAndPermissions(
            'cooperative_admin',
            ['users.create'],
        );

        self::assertFalse(Gate::forUser($admin)->allows('create', User::class));
    }

    public function test_root_can_change_own_status(): void
    {
        $root = $this->userWithRoleAndPermissions('super_admin_root', ['users.deactivate']);

        self::assertTrue(Gate::forUser($root)->allows('deactivate', $root));
    }

    public function test_super_admin_cannot_change_own_status(): void
    {
        $admin = $this->userWithRoleAndPermissions('super_admin', ['users.deactivate']);

        self::assertFalse(Gate::forUser($admin)->allows('deactivate', $admin));
    }

    public function test_super_admin_cannot_reactivate_a_suspended_user(): void
    {
        $cooperative = $this->createCooperative();
        $admin = $this->userWithRoleAndPermissions('super_admin', ['users.activate']);
        $admin->cooperatives()->attach($cooperative->getKey());

        $suspended = User::factory()->create([
            'cooperative_id' => $cooperative->getKey(),
            'status' => UserStatus::Suspended,
        ]);

        self::assertFalse(Gate::forUser($admin)->allows('activate', $suspended));
    }

    public function test_cooperative_admin_cannot_change_user_status(): void
    {
        $cooperative = $this->createCooperative();
        $admin = $this->userWithRoleAndPermissions(
            'cooperative_admin',
            ['users.activate', 'users.deactivate', 'users.suspend'],
            ['cooperative_id' => $cooperative->getKey()],
        );
        $target = User::factory()->create([
            'cooperative_id' => $cooperative->getKey(),
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

    private function createCooperative(): Cooperative
    {
        $country = Country::query()->firstOrCreate(
            ['code' => 'MA'],
            [
                'iso3' => 'MAR',
                'name' => 'Morocco',
                'native_name' => 'المغرب',
                'phone_code' => '+212',
                'currency' => 'MAD',
                'locale' => 'fr',
                'is_active' => true,
            ],
        );

        $address = Address::factory()->create([
            'country_id' => $country->getKey(),
        ]);

        return Cooperative::factory()->create([
            'address_id' => $address->getKey(),
        ]);
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
