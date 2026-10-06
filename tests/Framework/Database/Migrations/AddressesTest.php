<?php

declare(strict_types=1);

namespace Tests\Framework\Database\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class AddressesTest extends TestCase
{
    use RefreshDatabase;

    public function test_addresses_table_exists(): void
    {
        self::assertTrue(
            Schema::hasTable('addresses'),
        );
    }

    public function test_addresses_table_has_required_columns(): void
    {
        $columns = [
            'id',
            'address_line_1',
            'city',
            'country_id',
        ];

        foreach ($columns as $column) {
            self::assertTrue(
                Schema::hasColumn('addresses', $column),
                "Column [{$column}] does not exist.",
            );
        }
    }

    public function test_addresses_table_has_optional_columns(): void
    {
        $columns = [
            'address_line_2',
            'postal_code',
            'state',
            'latitude',
            'longitude',
        ];

        foreach ($columns as $column) {
            self::assertTrue(
                Schema::hasColumn('addresses', $column),
                "Column [{$column}] does not exist.",
            );
        }
    }

    public function test_addresses_table_has_audit_columns(): void
    {
        $columns = [
            'created_by',
            'updated_by',
            'deleted_by',
        ];

        foreach ($columns as $column) {
            self::assertTrue(
                Schema::hasColumn('addresses', $column),
                "Audit column [{$column}] does not exist.",
            );
        }
    }

    public function test_addresses_table_has_timestamps(): void
    {
        self::assertTrue(
            Schema::hasColumn('addresses', 'created_at'),
        );

        self::assertTrue(
            Schema::hasColumn('addresses', 'updated_at'),
        );
    }

    public function test_addresses_table_has_soft_delete_column(): void
    {
        self::assertTrue(
            Schema::hasColumn('addresses', 'deleted_at'),
        );
    }

    public function test_addresses_table_has_country_index(): void
    {
        $indexes = Schema::getIndexes('addresses');

        self::assertTrue(
            collect($indexes)->contains(
                fn (array $index): bool =>
                    $index['name'] === 'addresses_country_id_index'
                    && $index['columns'] === ['country_id'],
            ),
        );
    }

    public function test_addresses_table_has_city_index(): void
    {
        $indexes = Schema::getIndexes('addresses');

        self::assertTrue(
            collect($indexes)->contains(
                fn (array $index): bool =>
                    $index['name'] === 'addresses_city_index'
                    && $index['columns'] === ['city'],
            ),
        );
    }

    public function test_country_id_is_a_foreign_key_to_countries(): void
    {
        $foreignKeys = \Illuminate\Support\Facades\Schema::getForeignKeys('addresses');

        $foreignKey = collect($foreignKeys)
            ->first(
                fn (array $key): bool =>
                    $key['columns'] === ['country_id']
                    && $key['foreign_table'] === 'countries'
                    && $key['foreign_columns'] === ['id'],
            );

        self::assertNotNull($foreignKey);
    }

    public function test_address_cannot_reference_a_non_existing_country(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        \Illuminate\Support\Facades\DB::table('addresses')->insert([
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'address_line_1' => '10 Rue Test',
            'address_line_2' => null,
            'postal_code' => '20000',
            'city' => 'Casablanca',
            'state' => null,
            'country_id' => (string) \Illuminate\Support\Str::ulid(),
            'latitude' => null,
            'longitude' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_country_cannot_be_physically_deleted_when_used_by_an_address(): void
    {
        $countryId = (string) \Illuminate\Support\Str::ulid();

        \Illuminate\Support\Facades\DB::table('countries')->insert([
            'id' => $countryId,
            'code' => 'MA',
            'iso3' => 'MAR',
            'name' => 'Morocco',
            'native_name' => 'المغرب',
            'phone_code' => '+212',
            'currency' => 'MAD',
            'locale' => 'fr',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \Illuminate\Support\Facades\DB::table('addresses')->insert([
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'address_line_1' => '10 Rue Test',
            'address_line_2' => null,
            'postal_code' => '20000',
            'city' => 'Casablanca',
            'state' => null,
            'country_id' => $countryId,
            'latitude' => null,
            'longitude' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        \Illuminate\Support\Facades\DB::table('countries')
            ->where('id', $countryId)
            ->delete();
    }
}