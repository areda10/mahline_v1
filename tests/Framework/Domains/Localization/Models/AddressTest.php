<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Localization\Models;

use App\Domains\Localization\Models\Address;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AddressTest extends TestCase
{
    use RefreshDatabase;

    public function test_address_uses_the_expected_table(): void
    {
        $address = new Address();

        self::assertSame(
            'addresses',
            $address->getTable(),
        );
    }

    public function test_address_uses_soft_deletes(): void
    {
        $traits = class_uses_recursive(Address::class);

        self::assertArrayHasKey(
            SoftDeletes::class,
            $traits,
        );
    }

    public function test_address_has_expected_fillable_attributes(): void
    {
        $address = new Address();

        self::assertSame(
            [
                'address_line_1',
                'address_line_2',
                'postal_code',
                'city',
                'state',
                'country_id',
                'latitude',
                'longitude',
            ],
            $address->getFillable(),
        );
    }

    public function test_address_casts_coordinates_as_decimal(): void
    {
        $address = new Address();

        $casts = $address->getCasts();

        self::assertSame(
            'decimal:7',
            $casts['latitude'],
        );

        self::assertSame(
            'decimal:7',
            $casts['longitude'],
        );
    }

    public function test_address_casts_deleted_at_as_datetime(): void
    {
        $address = new Address();

        self::assertSame(
            'datetime',
            $address->getCasts()['deleted_at'],
        );
    }

    public function test_address_can_be_instantiated(): void
    {
        $address = new Address();

        self::assertInstanceOf(
            Address::class,
            $address,
        );
    }

    public function test_address_uses_ulid_primary_key_configuration(): void
    {
        $address = new Address();

        self::assertSame(
            'string',
            $address->getKeyType(),
        );

        self::assertFalse(
            $address->getIncrementing(),
        );
    }

    public function test_address_has_no_context_specific_foreign_keys(): void
    {
        $address = new Address();

        self::assertNotContains(
            'cooperative_id',
            $address->getFillable(),
        );

        self::assertNotContains(
            'user_id',
            $address->getFillable(),
        );

        self::assertNotContains(
            'client_id',
            $address->getFillable(),
        );

        self::assertNotContains(
            'order_id',
            $address->getFillable(),
        );

        self::assertNotContains(
            'relay_point_id',
            $address->getFillable(),
        );
    }
}
