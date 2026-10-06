<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Localization\Factories;

use App\Domains\Localization\Models\Address;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AddressFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_an_address(): void
    {
        $address = Address::factory()->create();

        self::assertInstanceOf(
            Address::class,
            $address,
        );

        self::assertDatabaseHas(
            'addresses',
            [
                'id' => $address->id,
            ],
        );
    }

    public function test_factory_generates_a_ulid_identifier(): void
    {
        $address = Address::factory()->create();

        self::assertTrue(
            Str::isUlid($address->id),
        );
    }

    public function test_factory_generates_required_attributes(): void
    {
        $address = Address::factory()->create();

        self::assertNotEmpty($address->address_line_1);
        self::assertNotEmpty($address->city);
        self::assertNotEmpty($address->country_id);
    }

    public function test_factory_generates_a_valid_country_ulid(): void
    {
        $address = Address::factory()->create();

        self::assertTrue(
            Str::isUlid($address->country_id),
        );
    }

    public function test_factory_generates_an_optional_address_line_2(): void
    {
        $address = Address::factory()->create([
            'address_line_2' => 'Appartement 12',
        ]);

        self::assertSame(
            'Appartement 12',
            $address->address_line_2,
        );
    }

    public function test_factory_generates_an_optional_postal_code(): void
    {
        $address = Address::factory()->create([
            'postal_code' => '20000',
        ]);

        self::assertSame(
            '20000',
            $address->postal_code,
        );
    }

    public function test_factory_generates_optional_state(): void
    {
        $address = Address::factory()->create([
            'state' => 'Casablanca-Settat',
        ]);

        self::assertSame(
            'Casablanca-Settat',
            $address->state,
        );
    }

    public function test_factory_accepts_coordinates(): void
    {
        $address = Address::factory()->create([
            'latitude' => 33.5731100,
            'longitude' => -7.5898430,
        ]);

        self::assertSame(
            '33.5731100',
            $address->latitude,
        );

        self::assertSame(
            '-7.5898430',
            $address->longitude,
        );
    }

    public function test_factory_can_create_multiple_addresses(): void
    {
        $addresses = Address::factory()->count(3)->create();

        self::assertCount(3, $addresses);

        self::assertCount(
            3,
            Address::query()->get(),
        );
    }
}
