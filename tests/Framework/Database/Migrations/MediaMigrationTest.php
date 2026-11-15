<?php

declare(strict_types=1);

namespace Tests\Framework\Database\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class MediaMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_media_table_exists(): void
    {
        self::assertTrue(
            Schema::hasTable('media'),
        );
    }

    public function test_media_table_has_required_columns(): void
    {
        $columns = [
            'id',
            'mediable_type',
            'mediable_id',
            'collection',
            'category',
            'disk',
            'path',
            'original_name',
            'mime_type',
            'extension',
            'size',
            'visibility',
            'is_primary',
            'sort_order',
            'metadata',
        ];

        foreach ($columns as $column) {
            self::assertTrue(
                Schema::hasColumn('media', $column),
                "Column [{$column}] does not exist.",
            );
        }
    }

    public function test_media_table_has_audit_columns(): void
    {
        $columns = [
            'created_by',
            'updated_by',
            'deleted_by',
        ];

        foreach ($columns as $column) {
            self::assertTrue(
                Schema::hasColumn('media', $column),
                "Audit column [{$column}] does not exist.",
            );
        }
    }

    public function test_media_audit_columns_are_nullable(): void
    {
        $columns = collect(
            Schema::getColumns('media'),
        );

        foreach (['created_by', 'updated_by', 'deleted_by'] as $name) {
            $column = $columns->firstWhere('name', $name);

            self::assertNotNull($column);
            self::assertTrue(
                $column['nullable'],
                "Audit column [{$name}] must be nullable.",
            );
        }
    }

    public function test_media_table_has_timestamps_and_soft_delete_column(): void
    {
        foreach (['created_at', 'updated_at', 'deleted_at'] as $column) {
            self::assertTrue(
                Schema::hasColumn('media', $column),
                "Column [{$column}] does not exist.",
            );
        }
    }

    public function test_media_table_has_ulid_primary_key(): void
    {
        $primaryIndex = collect(
            Schema::getIndexes('media'),
        )->firstWhere('primary', true);

        self::assertNotNull($primaryIndex);
        self::assertSame(['id'], $primaryIndex['columns']);

        $idColumn = collect(
            Schema::getColumns('media'),
        )->firstWhere('name', 'id');

        self::assertNotNull($idColumn);
        self::assertSame('char', $idColumn['type_name']);
        self::assertSame('char(26)', $idColumn['type']);
    }

    public function test_media_table_has_mediable_lookup_index(): void
    {
        $indexes = collect(
            Schema::getIndexes('media'),
        );

        self::assertTrue(
            $indexes->contains(
                fn (array $index): bool => $index['columns'] === [
                    'mediable_type',
                    'mediable_id',
                    'collection',
                    'deleted_at',
                ],
            ),
            'The mediable lookup index is missing.',
        );
    }
}
