<?php

declare(strict_types=1);

namespace Tests\Framework\Database\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class CreateRolesTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_roles_table_exists(): void
    {
        $this->assertTrue(
            Schema::hasTable('roles')
        );
    }

    public function test_roles_table_contains_all_required_columns(): void
    {
        $columns = [
            'id',
            'name',
            'slug',
            'description',
            'is_active',
            'created_at',
            'updated_at',
            'created_by',
            'updated_by',
            'deleted_by',
            'deleted_at',
        ];

        foreach ($columns as $column) {
            $this->assertTrue(
                Schema::hasColumn('roles', $column),
                "La colonne [{$column}] doit exister dans la table [roles]."
            );
        }
    }

    public function test_roles_table_has_ulid_primary_key(): void
    {
        $column = collect(
            Schema::getColumns('roles')
        )->firstWhere('name', 'id');

        $this->assertNotNull($column);

        $this->assertContains(
            strtolower($column['type_name']),
            ['char', 'varchar']
        );

        if (array_key_exists('length', $column)) {
            $this->assertSame(
                26,
                $column['length']
            );
        }

        $this->assertFalse(
            $column['nullable']
        );
    }

    public function test_required_role_columns_are_not_nullable(): void
    {
        $requiredColumns = [
            'name',
            'slug',
            'is_active',
        ];

        foreach ($requiredColumns as $columnName) {
            $column = collect(
                Schema::getColumns('roles')
            )->firstWhere('name', $columnName);

            $this->assertNotNull($column);

            $this->assertFalse(
                $column['nullable'],
                "La colonne [{$columnName}] ne doit pas être nullable."
            );
        }
    }

    public function test_optional_role_columns_are_nullable(): void
    {
        $nullableColumns = [
            'description',
            'created_at',
            'updated_at',
            'created_by',
            'updated_by',
            'deleted_by',
            'deleted_at',
        ];

        foreach ($nullableColumns as $columnName) {
            $column = collect(
                Schema::getColumns('roles')
            )->firstWhere('name', $columnName);

            $this->assertNotNull($column);

            $this->assertTrue(
                $column['nullable'],
                "La colonne [{$columnName}] doit être nullable."
            );
        }
    }

    public function test_audit_actor_columns_are_char_26(): void
    {
        $columns = Schema::getColumns('roles');

        foreach ([
            'created_by',
            'updated_by',
            'deleted_by',
        ] as $columnName) {
            $column = collect($columns)
                ->firstWhere('name', $columnName);

            $this->assertNotNull($column);

            /*
             * Depending on the database driver, Laravel may expose
             * CHAR(26) as char or varchar through Schema::getColumns().
             */
            $this->assertContains(
                strtolower($column['type_name']),
                ['char', 'varchar']
            );

            if (array_key_exists('length', $column)) {
                $this->assertSame(
                    26,
                    $column['length']
                );
            }
        }
    }

    public function test_is_active_has_true_default(): void
    {
        $column = collect(
            Schema::getColumns('roles')
        )->firstWhere('name', 'is_active');

        $this->assertNotNull($column);

        $default = $column['default'];

        $this->assertContains(
            strtolower(trim((string) $default, "'")),
            ['1', 'true']
        );
    }

    public function test_slug_is_unique(): void
    {
        $indexes = Schema::getIndexes('roles');

        $slugIndex = collect($indexes)->first(
            static function (array $index): bool {
                return $index['unique']
                    && $index['columns'] === ['slug'];
            }
        );

        $this->assertNotNull(
            $slugIndex,
            'La colonne [slug] doit posséder un index UNIQUE.'
        );
    }
}
