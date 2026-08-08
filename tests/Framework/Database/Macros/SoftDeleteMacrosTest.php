<?php

declare(strict_types=1);

namespace Tests\Framework\Database\Macros;

use App\Core\Database\Macros\SoftDeleteMacros;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class SoftDeleteMacrosTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        SoftDeleteMacros::register();
    }

    public function test_registers_soft_delete_column_macro(): void
    {
        $this->assertTrue(
            Blueprint::hasMacro('softDeleteColumn')
        );
    }

    public function test_soft_delete_column_creates_deleted_at_column(): void
    {
        Schema::create('soft_delete_macros_test', function (Blueprint $table): void {
            $table->id();

            $table->softDeleteColumn();
        });

        $this->assertTrue(
            Schema::hasColumn('soft_delete_macros_test', 'deleted_at')
        );
    }

    public function test_soft_delete_column_is_nullable(): void
    {
        Schema::create('soft_delete_macros_nullable_test', function (Blueprint $table): void {
            $table->id();

            $table->softDeleteColumn();
        });

        $columns = Schema::getColumns('soft_delete_macros_nullable_test');

        $deletedAt = collect($columns)
            ->firstWhere('name', 'deleted_at');

        $this->assertNotNull($deletedAt);

        $this->assertTrue(
            $deletedAt['nullable']
        );
    }

    public function test_soft_delete_column_accepts_custom_column_name(): void
    {
        Schema::create('soft_delete_macros_custom_test', function (Blueprint $table): void {
            $table->id();

            $table->softDeleteColumn('archived_at');
        });

        $this->assertTrue(
            Schema::hasColumn('soft_delete_macros_custom_test', 'archived_at')
        );

        $this->assertFalse(
            Schema::hasColumn('soft_delete_macros_custom_test', 'deleted_at')
        );
    }

    public function test_register_is_idempotent(): void
    {
        SoftDeleteMacros::register();
        SoftDeleteMacros::register();

        $this->assertTrue(
            Blueprint::hasMacro('softDeleteColumn')
        );
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('soft_delete_macros_test');
        Schema::dropIfExists('soft_delete_macros_nullable_test');
        Schema::dropIfExists('soft_delete_macros_custom_test');

        parent::tearDown();
    }
}