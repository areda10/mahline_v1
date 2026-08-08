<?php

declare(strict_types=1);

namespace Tests\Framework\Database\Macros;

use App\Core\Database\Macros\BlueprintMacros;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

final class BlueprintMacrosTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        BlueprintMacros::register();
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

    public function test_registers_audit_columns_macro(): void
    {
        $this->assertTrue(
            Blueprint::hasMacro('auditColumns')
        );
    }

    public function test_registers_foreign_key_macro(): void
    {
        $this->assertTrue(
            Blueprint::hasMacro('foreignKey')
        );
    }

    public function test_registers_index_macros(): void
    {
        $this->assertTrue(
            Blueprint::hasMacro('indexName')
        );

        $this->assertTrue(
            Blueprint::hasMacro('uniqueName')
        );

        $this->assertTrue(
            Blueprint::hasMacro('indexColumns')
        );

        $this->assertTrue(
            Blueprint::hasMacro('uniqueColumns')
        );
    }

    public function test_registers_soft_delete_column_macro(): void
    {
        $this->assertTrue(
            Blueprint::hasMacro('softDeleteColumn')
        );
    }

    public function test_register_is_idempotent(): void
    {
        BlueprintMacros::register();
        BlueprintMacros::register();

        $this->assertTrue(
            Blueprint::hasMacro('ulidPrimary')
        );

        $this->assertTrue(
            Blueprint::hasMacro('status')
        );

        $this->assertTrue(
            Blueprint::hasMacro('auditColumns')
        );

        $this->assertTrue(
            Blueprint::hasMacro('foreignKey')
        );

        $this->assertTrue(
            Blueprint::hasMacro('indexName')
        );

        $this->assertTrue(
            Blueprint::hasMacro('uniqueName')
        );

        $this->assertTrue(
            Blueprint::hasMacro('indexColumns')
        );

        $this->assertTrue(
            Blueprint::hasMacro('uniqueColumns')
        );

        $this->assertTrue(
            Blueprint::hasMacro('softDeleteColumn')
        );
    }
}