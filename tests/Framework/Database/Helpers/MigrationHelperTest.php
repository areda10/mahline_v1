<?php

declare(strict_types=1);

namespace Tests\Framework\Database\Helpers;

use App\Core\Database\Helpers\MigrationHelper;
use App\Core\Database\Macros\BlueprintMacros;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class MigrationHelperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        BlueprintMacros::register();
    }

    public function test_ulid_primary_creates_primary_key(): void
    {
        Schema::create('migration_helper_ulid_test', function (Blueprint $table): void {
            MigrationHelper::ulidPrimary($table);
        });

        $columns = Schema::getColumns('migration_helper_ulid_test');

        $this->assertTrue(
            collect($columns)
                ->contains(
                    static fn (array $column): bool =>
                        $column['name'] === 'id'
                )
        );

        $indexes = Schema::getIndexes('migration_helper_ulid_test');

        $this->assertTrue(
            collect($indexes)
                ->contains(
                    static fn (array $index): bool =>
                        $index['primary'] === true
                )
        );
    }

    public function test_ulid_primary_supports_custom_column(): void
    {
        Schema::create('migration_helper_ulid_custom_test', function (Blueprint $table): void {
            MigrationHelper::ulidPrimary(
                $table,
                'uuid'
            );
        });

        $this->assertTrue(
            Schema::hasColumn(
                'migration_helper_ulid_custom_test',
                'uuid'
            )
        );
    }

    public function test_audit_columns_creates_standard_columns(): void
    {
        Schema::create('migration_helper_audit_test', function (Blueprint $table): void {
            MigrationHelper::auditColumns($table);
        });

        $this->assertTrue(
            Schema::hasColumn(
                'migration_helper_audit_test',
                'created_at'
            )
        );

        $this->assertTrue(
            Schema::hasColumn(
                'migration_helper_audit_test',
                'updated_at'
            )
        );
    }

    public function test_soft_delete_column_creates_deleted_at(): void
    {
        Schema::create('migration_helper_soft_delete_test', function (Blueprint $table): void {
            MigrationHelper::softDeleteColumn($table);
        });

        $this->assertTrue(
            Schema::hasColumn(
                'migration_helper_soft_delete_test',
                'deleted_at'
            )
        );
    }

    public function test_soft_delete_column_supports_custom_column(): void
    {
        Schema::create('migration_helper_soft_delete_custom_test', function (Blueprint $table): void {
            MigrationHelper::softDeleteColumn(
                $table,
                'archived_at'
            );
        });

        $this->assertTrue(
            Schema::hasColumn(
                'migration_helper_soft_delete_custom_test',
                'archived_at'
            )
        );
    }

    public function test_status_creates_standard_status_column(): void
    {
        Schema::create('migration_helper_status_test', function (Blueprint $table): void {
            MigrationHelper::status($table);
        });

        $columns = Schema::getColumns('migration_helper_status_test');

        $status = collect($columns)
            ->firstWhere('name', 'status');

        $this->assertNotNull($status);

        $this->assertTrue(
            $status['nullable'] === false
        );
    }

    public function test_status_supports_custom_column_and_default(): void
    {
        Schema::create('migration_helper_status_custom_test', function (Blueprint $table): void {
            MigrationHelper::status(
                $table,
                'state',
                'pending'
            );
        });

        $columns = Schema::getColumns('migration_helper_status_custom_test');

        $state = collect($columns)
            ->firstWhere('name', 'state');

        $this->assertNotNull($state);

        $this->assertSame(
            "'pending'",
            $state['default']
        );
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('migration_helper_ulid_test');
        Schema::dropIfExists('migration_helper_ulid_custom_test');
        Schema::dropIfExists('migration_helper_audit_test');
        Schema::dropIfExists('migration_helper_soft_delete_test');
        Schema::dropIfExists('migration_helper_soft_delete_custom_test');
        Schema::dropIfExists('migration_helper_status_test');
        Schema::dropIfExists('migration_helper_status_custom_test');

        parent::tearDown();
    }
}