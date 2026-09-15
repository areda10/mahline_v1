<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Identity\Authorization;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class UserRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_role_table_exists(): void
    {
        $this->assertTrue(
            Schema::hasTable('user_role')
        );
    }

    public function test_user_role_has_expected_columns(): void
    {
        $this->assertTrue(
            Schema::hasColumns('user_role', [
                'user_id',
                'role_id',
            ])
        );
    }

    public function test_user_role_has_user_foreign_key(): void
    {
        $foreignKeys = Schema::getForeignKeys('user_role');

        $this->assertTrue(
            collect($foreignKeys)->contains(
                fn (array $foreignKey): bool =>
                    $foreignKey['columns'] === ['user_id']
                    && $foreignKey['foreign_table'] === 'users'
                    && $foreignKey['foreign_columns'] === ['id']
            )
        );
    }

    public function test_user_role_has_role_foreign_key(): void
    {
        $foreignKeys = Schema::getForeignKeys('user_role');

        $this->assertTrue(
            collect($foreignKeys)->contains(
                fn (array $foreignKey): bool =>
                    $foreignKey['columns'] === ['role_id']
                    && $foreignKey['foreign_table'] === 'roles'
                    && $foreignKey['foreign_columns'] === ['id']
            )
        );
    }

    public function test_user_role_has_unique_constraint(): void
    {
        $indexes = Schema::getIndexes('user_role');

        $this->assertTrue(
            collect($indexes)->contains(
                fn (array $index): bool =>
                    $index['unique'] === true
                    && $index['columns'] === [
                        'user_id',
                        'role_id',
                    ]
            )
        );
    }
}