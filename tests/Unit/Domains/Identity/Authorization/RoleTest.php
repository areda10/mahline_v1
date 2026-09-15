<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Identity\Authorization;

use App\Domains\Identity\Authorization\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class RoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_roles_table_exists(): void
    {
        $this->assertTrue(
            Schema::hasTable('roles')
        );
    }

    public function test_role_has_expected_columns(): void
    {
        $this->assertTrue(
            Schema::hasColumns('roles', [
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

    public function test_role_uses_ulid_primary_key(): void
    {
        $role = new Role();

        $this->assertSame('string', $role->getKeyType());
        $this->assertFalse($role->getIncrementing());
    }

    public function test_role_uses_roles_table(): void
    {
        $role = new Role();

        $this->assertSame('roles', $role->getTable());
    }

    public function test_role_casts_is_active_to_boolean(): void
    {
        $role = new Role([
            'name' => 'Administrator',
            'slug' => 'administrator',
            'is_active' => true,
        ]);

        $this->assertIsBool($role->is_active);
        $this->assertTrue($role->is_active);
    }

    public function test_role_uses_soft_deletes(): void
    {
        $role = new Role();

        $this->assertArrayHasKey(
            'Illuminate\Database\Eloquent\SoftDeletes',
            class_uses_recursive($role)
        );
    }
}