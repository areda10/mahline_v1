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
        self::assertSame('addresses', (new Address)->getTable());
    }

    public function test_address_uses_soft_deletes(): void
    {
        self::assertArrayHasKey(SoftDeletes::class, class_uses_recursive(Address::class));
    }

    public function test_address_has_expected_fillable_attributes(): void
    {
        self::assertSame([
            'number',
            'street',
            'floor',
            'address_line_1',
            'address_line_2',
            'postal_code',
            'city',
            'region',
            'state',
            'country_id',
            'user_id',
            'cooperative_id',
            'latitude',
            'longitude',
        ], (new Address)->getFillable());
    }

    public function test_address_casts_coordinates_as_decimal(): void
    {
        $casts = (new Address)->getCasts();

        self::assertSame('decimal:7', $casts['latitude']);
        self::assertSame('decimal:7', $casts['longitude']);
    }

    public function test_address_casts_deleted_at_as_datetime(): void
    {
        self::assertSame('datetime', (new Address)->getCasts()['deleted_at']);
    }

    public function test_address_can_be_instantiated(): void
    {
        self::assertInstanceOf(Address::class, new Address);
    }

    public function test_address_uses_ulid_primary_key_configuration(): void
    {
        $address = new Address;

        self::assertSame('string', $address->getKeyType());
        self::assertFalse($address->getIncrementing());
    }

    public function test_address_exposes_only_user_and_cooperative_ownership_keys(): void
    {
        $fillable = (new Address)->getFillable();

        self::assertContains('user_id', $fillable);
        self::assertContains('cooperative_id', $fillable);
        self::assertNotContains('client_id', $fillable);
        self::assertNotContains('order_id', $fillable);
        self::assertNotContains('relay_point_id', $fillable);
    }
}
