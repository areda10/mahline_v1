<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Countries\Models;

use App\Domains\Countries\Models\Country;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CountryTest extends TestCase
{
    use RefreshDatabase;

    public function test_country_uses_the_expected_table(): void
    {
        $country = new Country();

        self::assertSame(
            'countries',
            $country->getTable(),
        );
    }

    public function test_country_uses_soft_deletes(): void
    {
        $traits = class_uses_recursive(Country::class);

        self::assertArrayHasKey(
            SoftDeletes::class,
            $traits,
        );
    }

    public function test_country_has_expected_fillable_attributes(): void
    {
        $country = new Country();

        self::assertSame(
            [
                'code',
                'iso3',
                'name',
                'native_name',
                'phone_code',
                'currency',
                'locale',
                'is_active',
            ],
            $country->getFillable(),
        );
    }

    public function test_country_casts_is_active_as_boolean(): void
    {
        $country = new Country();

        self::assertSame(
            'boolean',
            $country->getCasts()['is_active'],
        );
    }

    public function test_country_casts_deleted_at_as_datetime(): void
    {
        $country = new Country();

        self::assertSame(
            'datetime',
            $country->getCasts()['deleted_at'],
        );
    }

    public function test_country_can_be_instantiated(): void
    {
        $country = new Country();

        self::assertInstanceOf(
            Country::class,
            $country,
        );
    }

    public function test_country_uses_ulid_primary_key_configuration(): void
    {
        $country = new Country();

        self::assertSame(
            'string',
            $country->getKeyType(),
        );

        self::assertFalse(
            $country->getIncrementing(),
        );
    }

    public function test_country_has_no_context_specific_foreign_keys(): void
    {
        $country = new Country();

        self::assertNotContains(
            'address_id',
            $country->getFillable(),
        );

        self::assertNotContains(
            'cooperative_id',
            $country->getFillable(),
        );

        self::assertNotContains(
            'user_id',
            $country->getFillable(),
        );

        self::assertNotContains(
            'client_id',
            $country->getFillable(),
        );

        self::assertNotContains(
            'relay_point_id',
            $country->getFillable(),
        );
    }
}