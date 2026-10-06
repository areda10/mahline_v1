<?php

declare(strict_types=1);

namespace Tests\Framework\Database\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class CountriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_countries_table_exists(): void
    {
        self::assertTrue(
            Schema::hasTable('countries'),
        );
    }

    public function test_countries_table_has_required_columns(): void
    {
        $columns = [
            'id',
            'code',
            'iso3',
            'name',
            'native_name',
            'phone_code',
            'currency',
            'locale',
            'is_active',
        ];

        foreach ($columns as $column) {
            self::assertTrue(
                Schema::hasColumn('countries', $column),
                "Column [{$column}] does not exist.",
            );
        }
    }

    public function test_countries_table_has_audit_columns(): void
    {
        $columns = [
            'created_by',
            'updated_by',
            'deleted_by',
        ];

        foreach ($columns as $column) {
            self::assertTrue(
                Schema::hasColumn('countries', $column),
                "Audit column [{$column}] does not exist.",
            );
        }
    }

    public function test_countries_table_has_timestamps(): void
    {
        self::assertTrue(
            Schema::hasColumn('countries', 'created_at'),
        );

        self::assertTrue(
            Schema::hasColumn('countries', 'updated_at'),
        );
    }

    public function test_countries_table_has_soft_delete_column(): void
    {
        self::assertTrue(
            Schema::hasColumn('countries', 'deleted_at'),
        );
    }

    public function test_countries_table_has_unique_code_index(): void
    {
        $indexes = Schema::getIndexes('countries');

        self::assertTrue(
            collect($indexes)->contains(
                fn (array $index): bool =>
                    $index['name'] === 'countries_code_unique'
                    && $index['columns'] === ['code']
                    && $index['unique'] === true,
            ),
        );
    }

    public function test_countries_table_has_unique_iso3_index(): void
    {
        $indexes = Schema::getIndexes('countries');

        self::assertTrue(
            collect($indexes)->contains(
                fn (array $index): bool =>
                    $index['name'] === 'countries_iso3_unique'
                    && $index['columns'] === ['iso3']
                    && $index['unique'] === true,
            ),
        );
    }

    public function test_countries_table_has_expected_indexes(): void
    {
        $indexes = Schema::getIndexes('countries');

        $expectedIndexes = [
            'countries_name_index',
            'countries_currency_index',
            'countries_locale_index',
            'countries_is_active_index',
        ];

        foreach ($expectedIndexes as $indexName) {
            self::assertTrue(
                collect($indexes)->contains(
                    fn (array $index): bool =>
                        $index['name'] === $indexName,
                ),
                "Index [{$indexName}] does not exist.",
            );
        }
    }

    public function test_countries_table_has_active_default(): void
    {
        $countryId = (string) str()->ulid();

        \DB::table('countries')->insert([
            'id' => $countryId,
            'code' => 'MA',
            'iso3' => 'MAR',
            'name' => 'Morocco',
            'native_name' => 'المغرب',
            'phone_code' => '+212',
            'currency' => 'MAD',
            'locale' => 'fr',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $country = \DB::table('countries')
            ->where('id', $countryId)
            ->first();

        self::assertNotNull($country);

        self::assertTrue(
            (bool) $country->is_active,
        );
    }
}