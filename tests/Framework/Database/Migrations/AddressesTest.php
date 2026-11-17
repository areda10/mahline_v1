<?php

declare(strict_types=1);

namespace Tests\Framework\Database\Migrations;

use App\Domains\Identity\Users\Models\User;
use App\Domains\Localization\Models\Address;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AddressesTest extends TestCase
{
    use RefreshDatabase;

    public function test_addresses_table_exists_with_v1_columns_only(): void
    {
        self::assertTrue(Schema::hasTable('addresses'));
        foreach ([
            'id', 'number', 'street', 'floor', 'postal_code', 'city', 'region',
            'country_id', 'user_id', 'cooperative_id', 'latitude', 'longitude',
            'is_primary', 'deletion_source', 'owner_deletion_batch_id', 'deleted_at',
        ] as $column) {
            self::assertTrue(Schema::hasColumn('addresses', $column), "Missing [{$column}].");
        }
        foreach (['address_line_1', 'address_line_2', 'state'] as $legacy) {
            self::assertFalse(Schema::hasColumn('addresses', $legacy), "Legacy [{$legacy}] must be removed.");
        }
    }

    public function test_addresses_have_audit_columns_and_timestamps(): void
    {
        foreach (['created_by', 'updated_by', 'deleted_by', 'created_at', 'updated_at', 'deleted_at'] as $column) {
            self::assertTrue(Schema::hasColumn('addresses', $column));
        }
    }

    public function test_database_enforces_one_active_primary_per_user_and_one_active_address_per_cooperative(): void
    {
        foreach ([
            'addresses_one_primary_per_user_unique',
            'addresses_one_primary_per_cooperative_unique',
            'addresses_one_active_address_per_cooperative_unique',
        ] as $name) {
            self::assertTrue(collect(Schema::getIndexes('addresses'))->contains(
                static fn (array $index): bool => $index['name'] === $name && $index['unique'],
            ));
        }
    }

    public function test_owner_and_country_foreign_keys_exist(): void
    {
        $keys = collect(Schema::getForeignKeys('addresses'));
        foreach ([
            ['country_id', 'countries'],
            ['user_id', 'users'],
            ['cooperative_id', 'cooperatives'],
        ] as [$column, $table]) {
            self::assertTrue($keys->contains(
                static fn (array $key): bool => $key['columns'] === [$column] && $key['foreign_table'] === $table,
            ));
        }
    }

    public function test_address_requires_exactly_one_owner(): void
    {
        $countryId = Address::factory()->raw()['country_id'];
        $this->expectException(QueryException::class);
        DB::table('addresses')->insert([
            'id' => (string) Str::ulid(), 'street' => 'Rue test', 'city' => 'Casablanca',
            'country_id' => $countryId, 'user_id' => null, 'cooperative_id' => null,
            'is_primary' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_address_country_foreign_key_is_restrictive(): void
    {
        $user = User::factory()->create();
        $this->expectException(QueryException::class);
        DB::table('addresses')->insert([
            'id' => (string) Str::ulid(), 'street' => 'Rue test', 'city' => 'Casablanca',
            'country_id' => (string) Str::ulid(), 'user_id' => $user->getKey(),
            'cooperative_id' => null, 'is_primary' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
