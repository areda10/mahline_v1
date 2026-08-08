<?php

declare(strict_types=1);

namespace Tests\Framework\Database\Macros;

use App\Core\Database\Macros\ColumnMacros;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class ColumnMacrosTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        ColumnMacros::register();
    }

    public function test_registers_ulid_primary_macro(): void
    {
        $this->assertTrue(
            Blueprint::hasMacro('ulidPrimary')
        );
    }

    public function test_registers_status_macro(): void
    {
        $this->assertTrue(
            Blueprint::hasMacro('status')
        );
    }

    public function test_does_not_register_a_custom_ulid_macro(): void
    {
        $this->assertFalse(
            Blueprint::hasMacro('ulid')
        );
    }

    public function test_ulid_primary_creates_ulid_primary_key(): void
    {
        Schema::create('column_macros_test', function (Blueprint $table): void {
            $table->ulidPrimary();
        });

        $this->assertTrue(
            Schema::hasColumn('column_macros_test', 'id')
        );
    }

    public function test_ulid_primary_accepts_custom_column_name(): void
    {
        Schema::create('column_macros_custom_ulid_test', function (Blueprint $table): void {
            $table->ulidPrimary('uuid');
        });

        $this->assertTrue(
            Schema::hasColumn('column_macros_custom_ulid_test', 'uuid')
        );
    }

    public function test_status_creates_status_column(): void
    {
        Schema::create('column_macros_status_test', function (Blueprint $table): void {
            $table->status();
        });

        $this->assertTrue(
            Schema::hasColumn('column_macros_status_test', 'status')
        );
    }

    public function test_status_accepts_custom_column_name(): void
    {
        Schema::create('column_macros_custom_status_test', function (Blueprint $table): void {
            $table->status('state');
        });

        $this->assertTrue(
            Schema::hasColumn('column_macros_custom_status_test', 'state')
        );
    }

    public function test_status_accepts_custom_default_value(): void
    {
        Schema::create('column_macros_custom_default_test', function (Blueprint $table): void {
            $table->status('status', 'inactive');
        });

        $this->assertTrue(
            Schema::hasColumn('column_macros_custom_default_test', 'status')
        );
    }

    public function test_register_is_idempotent(): void
    {
        ColumnMacros::register();
        ColumnMacros::register();

        $this->assertTrue(
            Blueprint::hasMacro('ulidPrimary')
        );

        $this->assertTrue(
            Blueprint::hasMacro('status')
        );
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('column_macros_test');
        Schema::dropIfExists('column_macros_custom_ulid_test');
        Schema::dropIfExists('column_macros_status_test');
        Schema::dropIfExists('column_macros_custom_status_test');
        Schema::dropIfExists('column_macros_custom_default_test');

        parent::tearDown();
    }
}