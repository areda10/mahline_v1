<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\UserManagement\Rules;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Countries\Models\Country;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Localization\Models\Address;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class UserManagementScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_be_assigned_to_multiple_cooperatives(): void
    {
        $superAdmin = User::factory()->create();

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

        $superAdmin->cooperatives()->attach([
            $cooperativeA->id,
            $cooperativeB->id,
        ]);

        self::assertCount(
            2,
            $superAdmin->cooperatives()->get(),
        );
    }

    public function test_super_admin_can_access_assigned_cooperative(): void
    {
        $superAdmin = User::factory()->create();

        $country = Country::factory()->create();

        $address = Address::factory()->create([
            'country_id' => $country->id,
        ]);

        $cooperative = Cooperative::factory()->create([
            'address_id' => $address->id,
        ]);

        $superAdmin->cooperatives()->attach(
            $cooperative->id,
        );

        self::assertTrue(
            $superAdmin->cooperatives()
                ->whereKey($cooperative->id)
                ->exists(),
        );
    }

    public function test_super_admin_cannot_access_unassigned_cooperative(): void
    {
        $superAdmin = User::factory()->create();

        $country = Country::factory()->create();

        $addressA = Address::factory()->create([
            'country_id' => $country->id,
        ]);

        $addressB = Address::factory()->create([
            'country_id' => $country->id,
        ]);

        $assignedCooperative = Cooperative::factory()->create([
            'address_id' => $addressA->id,
        ]);

        $unassignedCooperative = Cooperative::factory()->create([
            'address_id' => $addressB->id,
        ]);

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

    public function test_cooperative_user_belongs_to_only_one_cooperative(): void
    {
        $country = Country::factory()->create();

        $address = Address::factory()->create([
            'country_id' => $country->id,
        ]);

        $cooperative = Cooperative::factory()->create([
            'address_id' => $address->id,
        ]);

        $user = User::factory()->create([
            'cooperative_id' => $cooperative->id,
        ]);

        self::assertTrue(
            $user->cooperative->is($cooperative),
        );

        self::assertSame(
            $cooperative->id,
            $user->cooperative_id,
        );
    }

    public function test_super_admin_assignment_is_stored_in_pivot(): void
    {
        $superAdmin = User::factory()->create();

        $country = Country::factory()->create();

        $address = Address::factory()->create([
            'country_id' => $country->id,
        ]);

        $cooperative = Cooperative::factory()->create([
            'address_id' => $address->id,
        ]);

        $superAdmin->cooperatives()->attach(
            $cooperative->id,
        );

        self::assertDatabaseHas(
            'super_admin_cooperative',
            [
                'super_admin_id' => $superAdmin->id,
                'cooperative_id' => $cooperative->id,
            ],
        );
    }
}