<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Localization\Relations;

use App\Domains\Countries\Models\Country;
use App\Domains\Localization\Models\Address;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AddressCountryRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_address_defines_a_country_belongs_to_relationship(): void
    {
        $country = Country::factory()->create();

        $address = Address::factory()->create([
            'country_id' => $country->id,
        ]);

        self::assertInstanceOf(
            BelongsTo::class,
            $address->country(),
        );
    }

    public function test_country_defines_an_addresses_has_many_relationship(): void
    {
        $country = Country::factory()->create();

        self::assertInstanceOf(
            HasMany::class,
            $country->addresses(),
        );
    }

    public function test_address_country_relationship_uses_country_id(): void
    {
        $country = Country::factory()->create();

        $address = Address::factory()->create([
            'country_id' => $country->id,
        ]);

        self::assertSame(
            $country->id,
            $address->country_id,
        );

        self::assertSame(
            $country->id,
            $address->country->id,
        );
    }

    public function test_country_can_retrieve_its_addresses(): void
    {
        $country = Country::factory()->create();

        $address = Address::factory()->create([
            'country_id' => $country->id,
        ]);

        self::assertTrue(
            $country->addresses->contains($address),
        );
    }

    public function test_country_can_have_multiple_addresses(): void
    {
        $country = Country::factory()->create();

        $addresses = Address::factory()
            ->count(3)
            ->create([
                'country_id' => $country->id,
            ]);

        self::assertCount(
            3,
            $country->addresses,
        );

        foreach ($addresses as $address) {
            self::assertTrue(
                $country->addresses->contains($address),
            );
        }
    }

    public function test_address_belongs_to_only_its_assigned_country(): void
    {
        $morocco = Country::factory()->create([
            'code' => 'MA',
            'iso3' => 'MAR',
        ]);

        $france = Country::factory()->create([
            'code' => 'FR',
            'iso3' => 'FRA',
        ]);

        $address = Address::factory()->create([
            'country_id' => $morocco->id,
        ]);

        self::assertSame(
            $morocco->id,
            $address->country->id,
        );

        self::assertFalse(
            $france->addresses->contains($address),
        );
    }
}