<?php

declare(strict_types=1);

namespace Tests\Framework\Database\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_permission_table_exists(): void
    {
        $this->assertTrue(
            Schema::hasTable('role_permission'),
        );
    }

    public function test_role_permission_has_expected_columns(): void
    {
        $this->assertTrue(
            Schema::hasColumns('role_permission', [
                'role_id',
                'permission_id',
            ]),
        );
    }

    public function test_role_permission_has_role_foreign_key(): void
    {
        $foreignKeys = Schema::getForeignKeys('role_permission');

        $this->assertTrue(
            collect($foreignKeys)->contains(
                static fn (array $foreignKey): bool =>
                    $foreignKey['columns'] === ['role_id']
                    && $foreignKey['foreign_table'] === 'roles'
                    && $foreignKey['foreign_columns'] === ['id'],
            ),
        );
    }

    public function test_role_permission_has_permission_foreign_key(): void
    {
        $foreignKeys = Schema::getForeignKeys('role_permission');

        $this->assertTrue(
            collect($foreignKeys)->contains(
                static fn (array $foreignKey): bool =>
                    $foreignKey['columns'] === ['permission_id']
                    && $foreignKey['foreign_table'] === 'permissions'
                    && $foreignKey['foreign_columns'] === ['id'],
            ),
        );
    }

    public function test_role_permission_has_unique_constraint(): void
    {
        $indexes = Schema::getIndexes('role_permission');

        $this->assertTrue(
            collect($indexes)->contains(
                static fn (array $index): bool =>
                    $index['unique'] === true
                    && $index['columns'] === [
                        'role_id',
                        'permission_id',
                    ],
            ),
        );
    }

    public function test_role_permission_has_no_timestamps(): void
    {
        $this->assertFalse(
            Schema::hasColumns('role_permission', [
                'created_at',
                'updated_at',
            ]),
        );
    }

    public function test_role_permission_has_no_soft_delete_column(): void
    {
        $this->assertFalse(
            Schema::hasColumn('role_permission', 'deleted_at'),
        );
    }
}
