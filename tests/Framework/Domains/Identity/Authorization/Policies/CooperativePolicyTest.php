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

final class CooperativePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_root_can_view_any_cooperative_with_permission(): void
    {
        $user = $this->userWithRoleAndPermissions(
            'super_admin_root',
            ['cooperatives.view'],
        );

        $cooperative = $this->createCooperative();

        self::assertTrue(
            Gate::forUser($user)->allows('view', $cooperative),
        );
    }

    public function test_super_admin_can_view_an_assigned_cooperative(): void
    {
        $user = $this->userWithRoleAndPermissions(
            'super_admin',
            ['cooperatives.view'],
        );

        $cooperative = $this->createCooperative();

        $user->cooperatives()->attach($cooperative->id);

        self::assertTrue(
            Gate::forUser($user)->allows('view', $cooperative),
        );
    }

    public function test_super_admin_cannot_view_an_unassigned_cooperative(): void
    {
        $user = $this->userWithRoleAndPermissions(
            'super_admin',
            ['cooperatives.view'],
        );

        $cooperative = $this->createCooperative();

        self::assertFalse(
            Gate::forUser($user)->allows('view', $cooperative),
        );
    }

    public function test_cooperative_admin_can_view_own_cooperative(): void
    {
        $cooperative = $this->createCooperative();

        $user = $this->userWithRoleAndPermissions(
            'cooperative_admin',
            ['cooperatives.view'],
            ['cooperative_id' => $cooperative->id],
        );

        self::assertTrue(
            Gate::forUser($user)->allows('view', $cooperative),
        );
    }

    public function test_cooperative_admin_cannot_view_another_cooperative(): void
    {
        $ownCooperative = $this->createCooperative();
        $otherCooperative = $this->createCooperative();

        $user = $this->userWithRoleAndPermissions(
            'cooperative_admin',
            ['cooperatives.view'],
            ['cooperative_id' => $ownCooperative->id],
        );

        self::assertFalse(
            Gate::forUser($user)->allows('view', $otherCooperative),
        );
    }

    public function test_user_can_view_own_cooperative_with_permission(): void
    {
        $cooperative = $this->createCooperative();

        $user = $this->userWithRoleAndPermissions(
            'user',
            ['cooperatives.view'],
            ['cooperative_id' => $cooperative->id],
        );

        self::assertTrue(
            Gate::forUser($user)->allows('view', $cooperative),
        );
    }

    public function test_user_cannot_view_another_cooperative(): void
    {
        $ownCooperative = $this->createCooperative();
        $otherCooperative = $this->createCooperative();

        $user = $this->userWithRoleAndPermissions(
            'user',
            ['cooperatives.view'],
            ['cooperative_id' => $ownCooperative->id],
        );

        self::assertFalse(
            Gate::forUser($user)->allows('view', $otherCooperative),
        );
    }

    public function test_view_is_denied_without_functional_permission(): void
    {
        $cooperative = $this->createCooperative();

        $user = $this->userWithRoleAndPermissions(
            'cooperative_admin',
            [],
            ['cooperative_id' => $cooperative->id],
        );

        self::assertFalse(
            Gate::forUser($user)->allows('view', $cooperative),
        );
    }

    public function test_inactive_user_cannot_view_cooperative(): void
    {
        $cooperative = $this->createCooperative();

        $user = $this->userWithRoleAndPermissions(
            'super_admin_root',
            ['cooperatives.view'],
            ['status' => UserStatus::Suspended],
        );

        self::assertFalse(
            Gate::forUser($user)->allows('view', $cooperative),
        );
    }

    public function test_only_super_admin_root_can_create_cooperative(): void
    {
        $root = $this->userWithRoleAndPermissions(
            'super_admin_root',
            ['cooperatives.create'],
        );

        $superAdmin = $this->userWithRoleAndPermissions(
            'super_admin',
            ['cooperatives.create'],
        );

        self::assertTrue(
            Gate::forUser($root)->allows('create', Cooperative::class),
        );

        self::assertFalse(
            Gate::forUser($superAdmin)->allows('create', Cooperative::class),
        );
    }

    public function test_super_admin_root_cannot_create_without_permission(): void
    {
        $user = $this->userWithRoleAndPermissions(
            'super_admin_root',
            [],
        );

        self::assertFalse(
            Gate::forUser($user)->allows('create', Cooperative::class),
        );
    }

    public function test_update_requires_permission_and_access_to_cooperative(): void
    {
        $ownCooperative = $this->createCooperative();
        $otherCooperative = $this->createCooperative();

        $user = $this->userWithRoleAndPermissions(
            'cooperative_admin',
            ['cooperatives.update'],
            ['cooperative_id' => $ownCooperative->id],
        );

        self::assertTrue(
            Gate::forUser($user)->allows('update', $ownCooperative),
        );

        self::assertFalse(
            Gate::forUser($user)->allows('update', $otherCooperative),
        );
    }

    public function test_delete_requires_permission_and_access_to_cooperative(): void
    {
        $cooperative = $this->createCooperative();

        $user = $this->userWithRoleAndPermissions(
            'cooperative_admin',
            ['cooperatives.delete'],
            ['cooperative_id' => $cooperative->id],
        );

        self::assertTrue(
            Gate::forUser($user)->allows('delete', $cooperative),
        );

        $userWithoutDeletePermission = $this->userWithRoleAndPermissions(
            'user',
            ['cooperatives.view'],
            ['cooperative_id' => $cooperative->id],
        );

        self::assertFalse(
            Gate::forUser($userWithoutDeletePermission)
                ->allows('delete', $cooperative),
        );
    }

    public function test_view_any_requires_view_permission(): void
    {
        $user = $this->userWithRoleAndPermissions(
            'super_admin',
            [],
        );

        self::assertFalse(
            Gate::forUser($user)->allows('viewAny', Cooperative::class),
        );
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
            'country_id' => $country->id,
        ]);

        return Cooperative::factory()->create([
            'address_id' => $address->id,
        ]);
    }

    /**
     * @param array<string> $permissionSlugs
     * @param array<string, mixed> $userAttributes
     */
    private function userWithRoleAndPermissions(
        string $roleSlug,
        array $permissionSlugs,
        array $userAttributes = [],
    ): User {
        $user = User::factory()->create($userAttributes);

        $role = Role::query()->firstOrCreate(
            ['slug' => $roleSlug],
            [
                'name' => str($roleSlug)
                    ->replace('_', ' ')
                    ->title()
                    ->toString(),
                'is_active' => true,
            ],
        );

        foreach ($permissionSlugs as $permissionSlug) {
            $permission = Permission::query()->firstOrCreate(
                ['slug' => $permissionSlug],
                [
                    'name' => str($permissionSlug)
                        ->replace('.', ' ')
                        ->replace('_', ' ')
                        ->title()
                        ->toString(),
                    'is_active' => true,
                ],
            );

            $role->permissions()->syncWithoutDetaching([
                $permission->id,
            ]);
        }

        $user->roles()->syncWithoutDetaching([
            $role->id,
        ]);

        return $user;
    }
}