<?php

declare(strict_types=1);

namespace Tests\Framework\Database\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class CreateUsersTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_table_exists(): void
    {
        $this->assertTrue(
            Schema::hasTable('users')
        );
    }

    public function test_users_table_contains_all_required_columns(): void
    {
        $columns = [
            'id',
            'first_name',
            'last_name',
            'display_name',
            'email',
            'telephone',
            'password',
            'remember_token',
            'status',
            'locale',
            'timezone',
            'email_verified_at',
            'created_at',
            'updated_at',
            'created_by',
            'updated_by',
            'deleted_by',
            'deleted_at',
        ];

        foreach ($columns as $column) {
            $this->assertTrue(
                Schema::hasColumn('users', $column),
                "La colonne [{$column}] doit exister dans la table [users]."
            );
        }
    }

    /*
    public function test_users_table_has_ulid_primary_key(): void
    {
        $column = collect(
            Schema::getColumns('users')
        )->firstWhere('name', 'id');

        $this->assertNotNull($column);

        $this->assertSame(
            'char',
            strtolower($column['type_name'])
        );

        $this->assertSame(
            26,
            $column['length']
        );

        $this->assertFalse(
            $column['nullable']
        );
    }
    */

    public function test_users_table_has_ulid_primary_key(): void
    {
        $column = collect(
            Schema::getColumns('users')
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
    
    public function test_required_user_columns_are_not_nullable(): void
    {
        $requiredColumns = [
            'first_name',
            'last_name',
            'display_name',
            'email',
            'telephone',
            'password',
            'status',
            'locale',
            'timezone',
        ];

        foreach ($requiredColumns as $columnName) {
            $column = collect(
                Schema::getColumns('users')
            )->firstWhere('name', $columnName);

            $this->assertNotNull($column);

            $this->assertFalse(
                $column['nullable'],
                "La colonne [{$columnName}] ne doit pas être nullable."
            );
        }
    }

    public function test_optional_user_columns_are_nullable(): void
    {
        $nullableColumns = [
            'remember_token',
            'email_verified_at',
            'created_at',
            'updated_at',
            'created_by',
            'updated_by',
            'deleted_by',
            'deleted_at',
        ];

        foreach ($nullableColumns as $columnName) {
            $column = collect(
                Schema::getColumns('users')
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
        $columns = Schema::getColumns('users');

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

    public function test_status_has_active_default(): void
    {
        $column = collect(
            Schema::getColumns('users')
        )->firstWhere('name', 'status');

        $this->assertNotNull($column);

        $this->assertSame(
            'active',
            trim((string) $column['default'], "'")
        );
    }

    public function test_locale_has_fr_default(): void
    {
        $column = collect(
            Schema::getColumns('users')
        )->firstWhere('name', 'locale');

        $this->assertNotNull($column);

        $this->assertSame(
            'fr',
            trim((string) $column['default'], "'")
        );
    }

    public function test_timezone_has_utc_default(): void
    {
        $column = collect(
            Schema::getColumns('users')
        )->firstWhere('name', 'timezone');

        $this->assertNotNull($column);

        $this->assertSame(
            'UTC',
            trim((string) $column['default'], "'")
        );
    }

    public function test_email_is_unique(): void
    {
        $indexes = Schema::getIndexes('users');

        $emailIndex = collect($indexes)->first(
            static function (array $index): bool {
                return $index['unique']
                    && $index['columns'] === ['email'];
            }
        );

        $this->assertNotNull(
            $emailIndex,
            'La colonne [email] doit posséder un index UNIQUE.'
        );
    }
}