<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\UserManagement\Rules;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Countries\Models\Country;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Localization\Models\Address;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UserManagementRoleScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_root_has_global_scope(): void
    {
        $root = $this->createUserWithRole('super_admin_root');

        [$cooperativeA, $cooperativeB] = $this->createTwoCooperatives();

        self::assertTrue(
            $root->hasRole('super_admin_root'),
        );

        self::assertTrue(
            $root->hasRole('super_admin_root'),
        );

        self::assertNotSame(
            $cooperativeA->id,
            $cooperativeB->id,
        );
    }

    public function test_super_admin_is_scoped_to_assigned_cooperatives(): void
    {
        $superAdmin = $this->createUserWithRole('super_admin');

        [$assignedCooperative, $unassignedCooperative] =
            $this->createTwoCooperatives();

        $superAdmin->cooperatives()->attach(
            $assignedCooperative->id,
        );

        self::assertTrue(
            $superAdmin->cooperatives()
                ->whereKey($assignedCooperative->id)
                ->exists(),
        );

        self::assertFalse(
            $superAdmin->cooperatives()
                ->whereKey($unassignedCooperative->id)
                ->exists(),
        );
    }

    public function test_super_admin_can_manage_multiple_assigned_cooperatives(): void
    {
        $superAdmin = $this->createUserWithRole('super_admin');

        [$cooperativeA, $cooperativeB] = $this->createTwoCooperatives();

        $superAdmin->cooperatives()->attach([
            $cooperativeA->id,
            $cooperativeB->id,
        ]);

        self::assertCount(
            2,
            $superAdmin->cooperatives()->get(),
        );
    }

    public function test_cooperative_admin_belongs_to_one_cooperative(): void
    {
        $cooperative = $this->createCooperative();

        $admin = $this->createUserWithRole(
            'cooperative_admin',
            $cooperative->id,
        );

        self::assertSame(
            $cooperative->id,
            $admin->cooperative_id,
        );

        self::assertTrue(
            $admin->cooperative->is($cooperative),
        );
    }

    public function test_user_belongs_to_one_cooperative(): void
    {
        $cooperative = $this->createCooperative();

        $user = $this->createUserWithRole(
            'user',
            $cooperative->id,
        );

        self::assertSame(
            $cooperative->id,
            $user->cooperative_id,
        );

        self::assertTrue(
            $user->cooperative->is($cooperative),
        );
    }

    public function test_super_admin_role_does_not_define_a_cooperative_id(): void
    {
        $superAdmin = $this->createUserWithRole('super_admin');

        self::assertNull(
            $superAdmin->cooperative_id,
        );
    }

    private function createUserWithRole(
        string $roleSlug,
        ?string $cooperativeId = null,
    ): User {
        $user = User::factory()->create([
            'cooperative_id' => $cooperativeId,
        ]);

        $role = Role::query()->create([
            'name' => $roleSlug,
            'slug' => $roleSlug,
            'is_active' => true,
        ]);

        $user->roles()->attach($role);

        return $user;
    }

    private function createCooperative(): Cooperative
    {
        $country = Country::factory()->create();

        $address = Address::factory()->create([
            'country_id' => $country->id,
        ]);

        return Cooperative::factory()->create([
            'address_id' => $address->id,
        ]);
    }

    /**
     * @return array{Cooperative, Cooperative}
     */
    private function createTwoCooperatives(): array
    {
        $country = Country::factory()->create();

        $addressA = Address::factory()->create([
            'country_id' => $country->id,
        ]);

        $addressB = Address::factory()->create([
            'country_id' => $country->id,
        ]);

        $cooperativeA = Cooperative::factory()->create([
            'address_id' => $addressA->id,
        ]);

        $cooperativeB = Cooperative::factory()->create([
            'address_id' => $addressB->id,
        ]);

        return [
            $cooperativeA,
            $cooperativeB,
        ];
    }
}