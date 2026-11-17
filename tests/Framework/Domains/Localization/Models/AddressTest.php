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

    public function test_address_uses_expected_table_and_soft_deletes(): void
    {
        self::assertSame('addresses', (new Address)->getTable());
        self::assertArrayHasKey(SoftDeletes::class, class_uses_recursive(Address::class));
    }

    public function test_address_has_only_v1_fillable_attributes(): void
    {
        self::assertSame([
            'number', 'street', 'floor', 'postal_code', 'city', 'region',
            'country_id', 'user_id', 'cooperative_id', 'latitude', 'longitude', 'is_primary',
        ], (new Address)->getFillable());
    }

    public function test_address_casts_coordinates_primary_and_deleted_at(): void
    {
        $casts = (new Address)->getCasts();
        self::assertSame('decimal:7', $casts['latitude']);
        self::assertSame('decimal:7', $casts['longitude']);
        self::assertSame('boolean', $casts['is_primary']);
        self::assertSame('datetime', $casts['deleted_at']);
    }

    public function test_address_uses_ulid_primary_key_configuration(): void
    {
        $address = new Address;
        self::assertSame('string', $address->getKeyType());
        self::assertFalse($address->getIncrementing());
    }

    public function test_address_exposes_only_user_and_cooperative_owner_keys(): void
    {
        $fillable = (new Address)->getFillable();
        self::assertContains('user_id', $fillable);
        self::assertContains('cooperative_id', $fillable);
        self::assertNotContains('client_id', $fillable);
        self::assertNotContains('order_id', $fillable);
        self::assertNotContains('relay_point_id', $fillable);
    }
}
