<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Countries\Factories;

use App\Domains\Countries\Models\Country;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CountryFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_a_country(): void
    {
        $country = Country::factory()->create();

        self::assertInstanceOf(
            Country::class,
            $country,
        );

        self::assertDatabaseHas(
            'countries',
            [
                'id' => $country->id,
            ],
        );
    }

    public function test_factory_generates_a_ulid_identifier(): void
    {
        $country = Country::factory()->create();

        self::assertTrue(
            Str::isUlid($country->id),
        );
    }

    public function test_factory_generates_required_attributes(): void
    {
        $country = Country::factory()->create();

        self::assertNotEmpty($country->code);
        self::assertNotEmpty($country->iso3);
        self::assertNotEmpty($country->name);
        self::assertNotEmpty($country->native_name);
        self::assertNotEmpty($country->phone_code);
        self::assertNotEmpty($country->currency);
        self::assertNotEmpty($country->locale);
    }

    public function test_factory_generates_an_active_country_by_default(): void
    {
        $country = Country::factory()->create();

        self::assertTrue(
            $country->is_active,
        );
    }

    public function test_inactive_state_creates_an_inactive_country(): void
    {
        $country = Country::factory()
            ->inactive()
            ->create();

        self::assertFalse(
            $country->is_active,
        );
    }

    public function test_factory_generates_a_two_character_country_code(): void
    {
        $country = Country::factory()->create();

        self::assertSame(
            2,
            strlen($country->code),
        );
    }

    public function test_factory_generates_a_three_character_iso_code(): void
    {
        $country = Country::factory()->create();

        self::assertSame(
            3,
            strlen($country->iso3),
        );
    }

    public function test_factory_can_create_multiple_countries(): void
    {
        $countries = Country::factory()
            ->count(3)
            ->create();

        self::assertCount(3, $countries);

        self::assertCount(
            3,
            Country::query()->get(),
        );
    }

    public function test_factory_generates_unique_country_codes_and_iso_codes(): void
    {
        $countries = Country::factory()
            ->count(3)
            ->create();

        self::assertCount(
            3,
            $countries->pluck('code')->unique(),
        );

        self::assertCount(
            3,
            $countries->pluck('iso3')->unique(),
        );
    }
}