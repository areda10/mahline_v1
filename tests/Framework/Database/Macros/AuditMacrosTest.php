<?php

declare(strict_types=1);

namespace Tests\Framework\Database\Macros;

use App\Core\Database\Macros\AuditMacros;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class AuditMacrosTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        AuditMacros::register();
    }

    public function test_registers_audit_columns_macro(): void
    {
        $this->assertTrue(
            Blueprint::hasMacro('auditColumns')
        );
    }

    public function test_audit_columns_creates_created_at_column(): void
    {
        Schema::create('audit_macros_created_test', function (Blueprint $table): void {
            $table->auditColumns();
        });

        $this->assertTrue(
            Schema::hasColumn('audit_macros_created_test', 'created_at')
        );
    }

    public function test_audit_columns_creates_updated_at_column(): void
    {
        Schema::create('audit_macros_updated_test', function (Blueprint $table): void {
            $table->auditColumns();
        });

        $this->assertTrue(
            Schema::hasColumn('audit_macros_updated_test', 'updated_at')
        );
    }

    public function test_audit_columns_creates_both_timestamp_columns(): void
    {
        Schema::create('audit_macros_both_test', function (Blueprint $table): void {
            $table->auditColumns();
        });

        $this->assertTrue(
            Schema::hasColumn('audit_macros_both_test', 'created_at')
        );

        $this->assertTrue(
            Schema::hasColumn('audit_macros_both_test', 'updated_at')
        );
    }

    public function test_register_is_idempotent(): void
    {
        AuditMacros::register();
        AuditMacros::register();

        $this->assertTrue(
            Blueprint::hasMacro('auditColumns')
        );
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('audit_macros_created_test');
        Schema::dropIfExists('audit_macros_updated_test');
        Schema::dropIfExists('audit_macros_both_test');

        parent::tearDown();
    }
}