<?php

declare(strict_types=1);

namespace Tests\Framework\Database\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class UserRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_role_table_exists(): void
    {
        $this->assertTrue(
            Schema::hasTable('user_role'),
        );
    }

    public function test_user_role_has_expected_columns(): void
    {
        $this->assertTrue(
            Schema::hasColumns('user_role', [
                'user_id',
                'role_id',
            ]),
        );
    }

    public function test_user_role_has_user_foreign_key(): void
    {
        $foreignKeys = Schema::getForeignKeys('user_role');

        $foreignKey = collect($foreignKeys)->first(
            static fn ($foreignKey): bool =>
                $foreignKey->columns === ['user_id']
                && $foreignKey->foreignTable === 'users'
                && $foreignKey->foreignColumns === ['id'],
        );

        $this->assertNotNull($foreignKey);
    }

    public function test_user_role_has_role_foreign_key(): void
    {
        $foreignKeys = Schema::getForeignKeys('user_role');

        $foreignKey = collect($foreignKeys)->first(
            static fn ($foreignKey): bool =>
                $foreignKey->columns === ['role_id']
                && $foreignKey->foreignTable === 'roles'
                && $foreignKey->foreignColumns === ['id'],
        );

        $this->assertNotNull($foreignKey);
    }

    public function test_user_role_has_unique_constraint(): void
    {
        $indexes = Schema::getIndexes('user_role');

        $uniqueIndex = collect($indexes)->first(
            static fn ($index): bool =>
                $index['unique'] === true
                && $index['columns'] === [
                    'user_id',
                    'role_id',
                ],
        );

        $this->assertNotNull($uniqueIndex);
    }

    public function test_user_role_has_no_timestamps(): void
    {
        $this->assertFalse(
            Schema::hasColumns('user_role', [
                'created_at',
                'updated_at',
            ]),
        );
    }

    public function test_user_role_has_no_soft_delete_column(): void
    {
        $this->assertFalse(
            Schema::hasColumn('user_role', 'deleted_at'),
        );
    }
}