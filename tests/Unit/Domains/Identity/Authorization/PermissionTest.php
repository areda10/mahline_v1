<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Identity\Authorization;

use App\Domains\Identity\Authorization\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class PermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_permissions_table_exists(): void
    {
        $this->assertTrue(
            Schema::hasTable('permissions')
        );
    }

    public function test_permission_has_expected_columns(): void
    {
        $this->assertTrue(
            Schema::hasColumns('permissions', [
                'id',
                'name',
                'slug',
                'description',
                'is_active',
                'created_at',
                'updated_at',
                'deleted_at',
            ])
        );
    }

    public function test_permission_uses_ulid_primary_key(): void
    {
        $permission = new Permission();

        $this->assertSame('string', $permission->getKeyType());
        $this->assertFalse($permission->getIncrementing());
    }

    public function test_permission_uses_permissions_table(): void
    {
        $permission = new Permission();

        $this->assertSame('permissions', $permission->getTable());
    }

    public function test_permission_casts_is_active_to_boolean(): void
    {
        $permission = new Permission([
            'name' => 'Create Products',
            'slug' => 'create-products',
            'is_active' => true,
        ]);

        $this->assertIsBool($permission->is_active);
        $this->assertTrue($permission->is_active);
    }

    public function test_permission_uses_soft_deletes(): void
    {
        $permission = new Permission();

        $this->assertArrayHasKey(
            'Illuminate\Database\Eloquent\SoftDeletes',
            class_uses_recursive($permission)
        );
    }
}