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
}