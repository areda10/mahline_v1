<?php

declare(strict_types=1);

namespace Tests\Framework\Database\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class CooperativesTest extends TestCase
{
    use RefreshDatabase;

    public function test_cooperatives_table_exists(): void
    {
        self::assertTrue(
            Schema::hasTable('cooperatives'),
        );
    }

    public function test_cooperatives_table_has_required_columns(): void
    {
        $columns = [
            'id',
            'name',
            'rib',
            'address_id',
            'phone',
            'email',
            'ice',
            'status',
        ];

        foreach ($columns as $column) {
            self::assertTrue(
                Schema::hasColumn('cooperatives', $column),
                "Column [{$column}] does not exist.",
            );
        }
    }

    public function test_cooperatives_table_has_optional_columns(): void
    {
        $columns = [
            'tax_id',
            'rc',
        ];

        foreach ($columns as $column) {
            self::assertTrue(
                Schema::hasColumn('cooperatives', $column),
                "Column [{$column}] does not exist.",
            );
        }
    }

    public function test_cooperatives_table_has_audit_columns(): void
    {
        $columns = [
            'created_by',
            'updated_by',
            'deleted_by',
        ];

        foreach ($columns as $column) {
            self::assertTrue(
                Schema::hasColumn('cooperatives', $column),
                "Audit column [{$column}] does not exist.",
            );
        }
    }

    public function test_cooperatives_table_has_timestamps(): void
    {
        self::assertTrue(
            Schema::hasColumn('cooperatives', 'created_at'),
        );

        self::assertTrue(
            Schema::hasColumn('cooperatives', 'updated_at'),
        );
    }

    public function test_cooperatives_table_has_soft_delete_column(): void
    {
        self::assertTrue(
            Schema::hasColumn('cooperatives', 'deleted_at'),
        );
    }

    public function test_cooperatives_table_has_expected_indexes(): void
    {
        $indexes = collect(
            Schema::getIndexes('cooperatives'),
        )->pluck('name');

        self::assertTrue(
            $indexes->contains('cooperatives_address_id_index'),
        );

        self::assertTrue(
            $indexes->contains('cooperatives_status_index'),
        );

        self::assertTrue(
            $indexes->contains('cooperatives_email_index'),
        );

        self::assertTrue(
            $indexes->contains('cooperatives_ice_index'),
        );
    }

    public function test_cooperatives_status_defaults_to_pending(): void
    {
        $statusColumn = collect(
            Schema::getColumns('cooperatives'),
        )->firstWhere('name', 'status');

        self::assertNotNull($statusColumn);

        self::assertSame(
            'pending',
            $statusColumn['default'],
        );
    }

    public function test_address_id_is_a_foreign_key_to_addresses(): void
    {
        $foreignKeys = \Illuminate\Support\Facades\Schema::getForeignKeys(
            'cooperatives',
        );

        $foreignKey = collect($foreignKeys)
            ->first(
                fn (array $key): bool =>
                    $key['columns'] === ['address_id']
                    && $key['foreign_table'] === 'addresses'
                    && $key['foreign_columns'] === ['id'],
            );

        self::assertNotNull($foreignKey);
    }

}
