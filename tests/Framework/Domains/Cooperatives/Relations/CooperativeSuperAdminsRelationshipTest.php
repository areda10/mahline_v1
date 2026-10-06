<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Cooperatives\Relations;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Countries\Models\Country;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Localization\Models\Address;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class CooperativeSuperAdminsRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_cooperative_defines_super_admins_belongs_to_many_relationship(): void
    {
        $cooperative = new Cooperative();

        self::assertInstanceOf(
            BelongsToMany::class,
            $cooperative->superAdmins(),
        );
    }

    public function test_cooperative_super_admins_relationship_uses_expected_pivot(): void
    {
        $cooperative = new Cooperative();

        $relation = $cooperative->superAdmins();

        self::assertSame(
            'super_admin_cooperative',
            $relation->getTable(),
        );

        self::assertSame(
            'cooperative_id',
            $relation->getForeignPivotKeyName(),
        );

        self::assertSame(
            'super_admin_id',
            $relation->getRelatedPivotKeyName(),
        );
    }

    public function test_cooperative_can_retrieve_multiple_super_admins(): void
    {
        $country = Country::factory()->create();

        $address = Address::factory()->create([
            'country_id' => $country->id,
        ]);

        $cooperative = Cooperative::factory()->create([
            'address_id' => $address->id,
        ]);

        $superAdminA = User::factory()->create();
        $superAdminB = User::factory()->create();

        $cooperative->superAdmins()->attach([
            $superAdminA->id,
            $superAdminB->id,
        ]);

        $cooperative->load('superAdmins');

        self::assertCount(
            2,
            $cooperative->superAdmins,
        );

        self::assertTrue(
            $cooperative->superAdmins->contains($superAdminA),
        );

        self::assertTrue(
            $cooperative->superAdmins->contains($superAdminB),
        );
    }

    public function test_super_admin_can_manage_multiple_cooperatives(): void
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

        $superAdmin = User::factory()->create();

        $cooperativeA->superAdmins()->attach($superAdmin->id);
        $cooperativeB->superAdmins()->attach($superAdmin->id);

        self::assertSame(
            2,
            DB::table('super_admin_cooperative')
                ->where('super_admin_id', $superAdmin->id)
                ->count(),
        );
    }

    public function test_cooperative_can_detach_one_super_admin_without_affecting_others(): void
    {
        $country = Country::factory()->create();

        $address = Address::factory()->create([
            'country_id' => $country->id,
        ]);

        $cooperative = Cooperative::factory()->create([
            'address_id' => $address->id,
        ]);

        $superAdminA = User::factory()->create();
        $superAdminB = User::factory()->create();

        $cooperative->superAdmins()->attach([
            $superAdminA->id,
            $superAdminB->id,
        ]);

        $cooperative->superAdmins()->detach($superAdminA->id);

        $cooperative->load('superAdmins');

        self::assertCount(
            1,
            $cooperative->superAdmins,
        );

        self::assertFalse(
            $cooperative->superAdmins->contains($superAdminA),
        );

        self::assertTrue(
            $cooperative->superAdmins->contains($superAdminB),
        );
    }
}