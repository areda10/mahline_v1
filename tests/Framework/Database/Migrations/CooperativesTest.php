<?php

declare(strict_types=1);

namespace Tests\Framework\Database\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class CooperativesTest extends TestCase
{
    use RefreshDatabase;

    public function test_cooperatives_table_has_no_address_pointer(): void
    {
        self::assertTrue(Schema::hasTable('cooperatives'));
        foreach (['id', 'name', 'rib', 'phone', 'email', 'ice', 'status'] as $column) {
            self::assertTrue(Schema::hasColumn('cooperatives', $column));
        }
        self::assertFalse(Schema::hasColumn('cooperatives', 'address_id'));
    }

    public function test_cooperatives_have_expected_optional_and_audit_columns(): void
    {
        foreach (['tax_id', 'rc', 'created_by', 'updated_by', 'deleted_by', 'created_at', 'updated_at', 'deleted_at'] as $column) {
            self::assertTrue(Schema::hasColumn('cooperatives', $column));
        }
    }

    public function test_cooperatives_have_expected_indexes(): void
    {
        $indexes = collect(Schema::getIndexes('cooperatives'))->pluck('name');
        foreach (['cooperatives_status_index', 'cooperatives_email_index', 'cooperatives_ice_index'] as $name) {
            self::assertTrue($indexes->contains($name));
        }
    }

    public function test_cooperative_status_defaults_to_pending(): void
    {
        $status = collect(Schema::getColumns('cooperatives'))->firstWhere('name', 'status');
        self::assertNotNull($status);
        self::assertSame('pending', $status['default']);
    }
}
