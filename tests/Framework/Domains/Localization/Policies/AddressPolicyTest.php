<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Localization\Policies;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authorization\Models\Permission;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Localization\Policies\AddressPolicy;
use App\Domains\Localization\Contracts\UserAddressOwnerGateway;
use App\Domains\Localization\Contracts\CooperativeAddressOwnerGateway;
use App\Shared\Contracts\ActorContextProvider;
use App\Shared\DTOs\ActorContext;
use App\Domains\Localization\DTOs\UserAddressOwnerData;
use App\Domains\Localization\DTOs\CooperativeAddressOwnerData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AddressPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_role_can_update_its_own_personal_address(): void
    {
        foreach (['super_admin_root', 'super_admin', 'cooperative_admin', 'user', 'customer'] as $role) {
            $actor = $this->userWithRoleAndPermissions($role, []);

            self::assertTrue(
                app(AddressPolicy::class)->updateUserAddress($this->actorContext($actor), $this->userOwner($actor)),
                sprintf('Le rôle %s doit pouvoir modifier sa propre adresse.', $role),
            );
        }
    }

    public function test_root_can_update_any_users_personal_address(): void
    {
        $root = $this->userWithRoleAndPermissions('super_admin_root', ['users.update']);
        $target = User::factory()->create();

        self::assertTrue(app(AddressPolicy::class)->updateUserAddress($this->actorContext($root), $this->userOwner($target)));
    }

    public function test_super_admin_can_update_personal_address_of_user_in_assigned_cooperative(): void
    {
        $cooperative = $this->createCooperative();
        $admin = $this->userWithRoleAndPermissions('super_admin', ['users.update']);
        $admin->cooperatives()->attach($cooperative->getKey());
        $target = $this->userWithRoleAndPermissions(
            'user',
            [],
            ['cooperative_id' => $cooperative->getKey()],
        );

        self::assertTrue(app(AddressPolicy::class)->updateUserAddress($this->actorContext($admin), $this->userOwner($target)));
    }

    public function test_super_admin_cannot_update_personal_address_outside_assigned_cooperatives(): void
    {
        $cooperative = $this->createCooperative();
        $admin = $this->userWithRoleAndPermissions('super_admin', ['users.update']);
        $target = User::factory()->create(['cooperative_id' => $cooperative->getKey()]);

        self::assertFalse(app(AddressPolicy::class)->updateUserAddress($admin, $target));
    }

    public function test_cooperative_admin_can_update_personal_address_of_user_in_own_cooperative(): void
    {
        $cooperative = $this->createCooperative();
        $admin = $this->userWithRoleAndPermissions(
            'cooperative_admin',
            ['users.update'],
            ['cooperative_id' => $cooperative->getKey()],
        );
        $target = $this->userWithRoleAndPermissions(
            'user',
            [],
            ['cooperative_id' => $cooperative->getKey()],
        );

        self::assertTrue(app(AddressPolicy::class)->updateUserAddress($admin, $target));
    }

    public function test_cooperative_admin_cannot_update_personal_address_outside_own_cooperative(): void
    {
        $ownCooperative = $this->createCooperative();
        $otherCooperative = $this->createCooperative();
        $admin = $this->userWithRoleAndPermissions(
            'cooperative_admin',
            ['users.update'],
            ['cooperative_id' => $ownCooperative->getKey()],
        );
        $target = $this->userWithRoleAndPermissions(
            'user',
            [],
            ['cooperative_id' => $otherCooperative->getKey()],
        );

        self::assertFalse(app(AddressPolicy::class)->updateUserAddress($admin, $target));
    }

    public function test_user_and_customer_cannot_update_another_users_personal_address(): void
    {
        foreach (['user', 'customer'] as $role) {
            $actor = $this->userWithRoleAndPermissions($role, ['users.update']);
            $target = User::factory()->create();

            self::assertFalse(app(AddressPolicy::class)->updateUserAddress($actor, $target));
        }
    }

    public function test_super_admin_cannot_update_personal_address_of_customer_without_cooperative_scope(): void
    {
        $admin = $this->userWithRoleAndPermissions('super_admin', ['users.update']);
        $customer = $this->userWithRoleAndPermissions('customer', []);

        self::assertFalse(app(AddressPolicy::class)->updateUserAddress($this->actorContext($admin), $this->userOwner($customer)));
    }

    public function test_personal_address_update_requires_users_update_permission_for_other_users(): void
    {
        $root = $this->userWithRoleAndPermissions('super_admin_root', []);
        $target = User::factory()->create();

        self::assertFalse(app(AddressPolicy::class)->updateUserAddress($this->actorContext($root), $this->userOwner($target)));
    }

    public function test_root_can_update_any_cooperative_address(): void
    {
        $root = $this->userWithRoleAndPermissions('super_admin_root', ['cooperatives.update']);
        $cooperative = $this->createCooperative();

        self::assertTrue(app(AddressPolicy::class)->updateCooperativeAddress($this->actorContext($root), $this->cooperativeOwner($cooperative)));
    }

    public function test_super_admin_can_update_only_assigned_cooperative_address(): void
    {
        $assigned = $this->createCooperative();
        $unassigned = $this->createCooperative();
        $admin = $this->userWithRoleAndPermissions('super_admin', ['cooperatives.update']);
        $admin->cooperatives()->attach($assigned->getKey());

        self::assertTrue(app(AddressPolicy::class)->updateCooperativeAddress($this->actorContext($admin), $this->cooperativeOwner($assigned)));
        self::assertFalse(app(AddressPolicy::class)->updateCooperativeAddress($this->actorContext($admin), $this->cooperativeOwner($unassigned)));
    }

    public function test_cooperative_admin_can_update_only_own_cooperative_address(): void
    {
        $own = $this->createCooperative();
        $other = $this->createCooperative();
        $admin = $this->userWithRoleAndPermissions(
            'cooperative_admin',
            ['cooperatives.update'],
            ['cooperative_id' => $own->getKey()],
        );

        self::assertTrue(app(AddressPolicy::class)->updateCooperativeAddress($this->actorContext($admin), $this->cooperativeOwner($own)));
        self::assertFalse(app(AddressPolicy::class)->updateCooperativeAddress($this->actorContext($admin), $this->cooperativeOwner($other)));
    }

    public function test_user_and_customer_cannot_update_cooperative_addresses(): void
    {
        $cooperative = $this->createCooperative();

        foreach (['user', 'customer'] as $role) {
            $actor = $this->userWithRoleAndPermissions($role, ['cooperatives.update']);

            self::assertFalse(app(AddressPolicy::class)->updateCooperativeAddress($this->actorContext($actor), $this->cooperativeOwner($cooperative)));
        }
    }

    public function test_cooperative_address_update_requires_cooperatives_update_permission(): void
    {
        $cooperative = $this->createCooperative();
        $root = $this->userWithRoleAndPermissions('super_admin_root', []);

        self::assertFalse(app(AddressPolicy::class)->updateCooperativeAddress($this->actorContext($root), $this->cooperativeOwner($cooperative)));
    }


    private function actorContext(User $user): ActorContext
    {
        return app(ActorContextProvider::class)->forUserId((string) $user->getKey());
    }

    private function userOwner(User $user): UserAddressOwnerData
    {
        return app(UserAddressOwnerGateway::class)->lock((string) $user->getKey());
    }

    private function cooperativeOwner(Cooperative $cooperative): CooperativeAddressOwnerData
    {
        return app(CooperativeAddressOwnerGateway::class)->lock((string) $cooperative->getKey());
    }

    private function createCooperative(): Cooperative
    {
        return Cooperative::factory()->create();
    }

    /**
     * @param  list<string>  $permissionSlugs
     * @param  array<string, mixed>  $attributes
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
