<?php

declare(strict_types=1);

namespace Tests\Framework\Database\Helpers;

use App\Core\Database\Helpers\SchemaHelper;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class SchemaHelperTest extends TestCase
{
    public function test_has_audit_columns_returns_true_when_both_columns_exist(): void
    {
        Schema::create('schema_helper_audit_test', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });

        $this->assertTrue(
            SchemaHelper::hasAuditColumns(
                'schema_helper_audit_test'
            )
        );
    }

    public function test_has_audit_columns_returns_false_when_created_at_is_missing(): void
    {
        Schema::create('schema_helper_audit_missing_created_test', function (Blueprint $table): void {
            $table->id();
            $table->timestamp('updated_at')->nullable();
        });

        $this->assertFalse(
            SchemaHelper::hasAuditColumns(
                'schema_helper_audit_missing_created_test'
            )
        );
    }

    public function test_has_audit_columns_returns_false_when_updated_at_is_missing(): void
    {
        Schema::create('schema_helper_audit_missing_updated_test', function (Blueprint $table): void {
            $table->id();
            $table->timestamp('created_at')->nullable();
        });

        $this->assertFalse(
            SchemaHelper::hasAuditColumns(
                'schema_helper_audit_missing_updated_test'
            )
        );
    }

    public function test_has_audit_columns_returns_false_when_both_columns_are_missing(): void
    {
        Schema::create('schema_helper_audit_missing_both_test', function (Blueprint $table): void {
            $table->id();
        });

        $this->assertFalse(
            SchemaHelper::hasAuditColumns(
                'schema_helper_audit_missing_both_test'
            )
        );
    }

    public function test_has_audit_columns_returns_false_for_unknown_table(): void
    {
        $this->assertFalse(
            SchemaHelper::hasAuditColumns(
                'schema_helper_unknown_table'
            )
        );
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('schema_helper_audit_test');
        Schema::dropIfExists('schema_helper_audit_missing_created_test');
        Schema::dropIfExists('schema_helper_audit_missing_updated_test');
        Schema::dropIfExists('schema_helper_audit_missing_both_test');

        parent::tearDown();
    }
}