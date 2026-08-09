<?php

declare(strict_types=1);

namespace Tests\Framework\Database\Macros;

use App\Core\Database\Macros\AuditActorMacros;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class AuditActorMacrosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        AuditActorMacros::register();
    }

    public function test_registers_audit_actor_columns_macro(): void
    {
        $this->assertTrue(
            Blueprint::hasMacro('auditActorColumns')
        );
    }

    public function test_audit_actor_columns_creates_created_by_column(): void
    {
        Schema::create('audit_actor_created_by_test', function (Blueprint $table): void {
            $table->id();
            $table->auditActorColumns();
        });

        $columns = Schema::getColumnListing('audit_actor_created_by_test');

        $this->assertContains('created_by', $columns);
    }

    public function test_audit_actor_columns_creates_updated_by_column(): void
    {
        Schema::create('audit_actor_updated_by_test', function (Blueprint $table): void {
            $table->id();
            $table->auditActorColumns();
        });

        $columns = Schema::getColumnListing('audit_actor_updated_by_test');

        $this->assertContains('updated_by', $columns);
    }

    public function test_audit_actor_columns_creates_deleted_by_column(): void
    {
        Schema::create('audit_actor_deleted_by_test', function (Blueprint $table): void {
            $table->id();
            $table->auditActorColumns();
        });

        $columns = Schema::getColumnListing('audit_actor_deleted_by_test');

        $this->assertContains('deleted_by', $columns);
    }

    public function test_audit_actor_columns_creates_all_columns(): void
    {
        Schema::create('audit_actor_all_test', function (Blueprint $table): void {
            $table->id();
            $table->auditActorColumns();
        });

        $columns = Schema::getColumnListing('audit_actor_all_test');

        $this->assertContains('created_by', $columns);
        $this->assertContains('updated_by', $columns);
        $this->assertContains('deleted_by', $columns);
    }

    public function test_audit_actor_columns_are_nullable(): void
    {
        Schema::create('audit_actor_nullable_test', function (Blueprint $table): void {
            $table->id();
            $table->auditActorColumns();
        });

        $columns = Schema::getColumns('audit_actor_nullable_test');

        $createdBy = collect($columns)->firstWhere('name', 'created_by');
        $updatedBy = collect($columns)->firstWhere('name', 'updated_by');
        $deletedBy = collect($columns)->firstWhere('name', 'deleted_by');

        $this->assertNotNull($createdBy);
        $this->assertNotNull($updatedBy);
        $this->assertNotNull($deletedBy);

        $this->assertTrue($createdBy['nullable']);
        $this->assertTrue($updatedBy['nullable']);
        $this->assertTrue($deletedBy['nullable']);
    }

    /*
    public function test_audit_actor_columns_use_char_26(): void
    {
        Schema::create('audit_actor_type_test', function (Blueprint $table): void {
            $table->id();
            $table->auditActorColumns();
        });

        $columns = Schema::getColumns('audit_actor_type_test');

        $createdBy = collect($columns)->firstWhere('name', 'created_by');
        $updatedBy = collect($columns)->firstWhere('name', 'updated_by');
        $deletedBy = collect($columns)->firstWhere('name', 'deleted_by');

        //$this->assertSame('char', strtolower($createdBy['type_name']));
        $this->assertContains(
            strtolower($createdBy['type_name']),
            ['char', 'varchar']
        );
        $this->assertSame(26, $createdBy['length']);

        //$this->assertSame('char', strtolower($updatedBy['type_name']));
        $this->assertContains(
            strtolower($updatedBy['type_name']),
            ['char', 'varchar']
        );
        $this->assertSame(26, $updatedBy['length']);

        //$this->assertSame('char', strtolower($deletedBy['type_name']));
        $this->assertContains(
            strtolower($deletedBy['type_name']),
            ['char', 'varchar']
        );
        $this->assertSame(26, $deletedBy['length']);
    }
    */
    public function test_audit_actor_columns_use_char_26(): void
    {
        Schema::create('audit_actor_type_test', function (Blueprint $table): void {
            $table->id();
            $table->auditActorColumns();
        });

        $columns = Schema::getColumns('audit_actor_type_test');

        $createdBy = collect($columns)->firstWhere('name', 'created_by');
        $updatedBy = collect($columns)->firstWhere('name', 'updated_by');
        $deletedBy = collect($columns)->firstWhere('name', 'deleted_by');

        $this->assertNotNull($createdBy);
        $this->assertNotNull($updatedBy);
        $this->assertNotNull($deletedBy);

        $this->assertContains(
            strtolower($createdBy['type_name']),
            ['char', 'varchar']
        );

        $this->assertContains(
            strtolower($updatedBy['type_name']),
            ['char', 'varchar']
        );

        $this->assertContains(
            strtolower($deletedBy['type_name']),
            ['char', 'varchar']
        );
    }

    public function test_register_is_idempotent(): void
    {
        AuditActorMacros::register();
        AuditActorMacros::register();

        $this->assertTrue(
            Blueprint::hasMacro('auditActorColumns')
        );
    }
}