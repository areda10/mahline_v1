<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Localization\Factories;

use App\Domains\Identity\Users\Models\User;
use App\Domains\Localization\Models\Address;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AddressFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_an_address_for_an_explicit_user_owner(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->forUser($user)->create();
        self::assertInstanceOf(Address::class, $address);
        self::assertDatabaseHas('addresses', ['id' => $address->getKey(), 'user_id' => $user->getKey()]);
    }

    public function test_factory_generates_a_ulid_identifier(): void
    {
        $address = Address::factory()->forUser(User::factory()->create())->create();
        self::assertTrue(Str::isUlid($address->getKey()));
    }

    public function test_factory_generates_v1_address_fields(): void
    {
        $address = Address::factory()->forUser(User::factory()->create())->create();
        self::assertNotEmpty($address->street);
        self::assertNotEmpty($address->city);
        self::assertNotEmpty($address->country_id);
        self::assertNull($address->cooperative_id);
    }

    public function test_factory_accepts_optional_address_fields_and_coordinates(): void
    {
        $address = Address::factory()->forUser(User::factory()->create())->create([
            'number' => '10', 'floor' => '2e étage', 'postal_code' => '20000',
            'region' => 'Casablanca-Settat', 'latitude' => 33.5731100, 'longitude' => -7.5898430,
        ]);
        self::assertSame('10', $address->number);
        self::assertSame('2e étage', $address->floor);
        self::assertSame('20000', $address->postal_code);
        self::assertSame('Casablanca-Settat', $address->region);
        self::assertSame('33.5731100', $address->latitude);
        self::assertSame('-7.5898430', $address->longitude);
    }

    public function test_factory_can_create_multiple_addresses_with_a_single_primary(): void
    {
        $user = User::factory()->create();
        $addresses = Address::factory()->forUser($user)->count(3)->sequence(
            ['is_primary' => true],
            ['is_primary' => false],
            ['is_primary' => false],
        )->create();
        self::assertCount(3, $addresses);
        self::assertSame(1, $addresses->where('is_primary', true)->count());
    }
}
