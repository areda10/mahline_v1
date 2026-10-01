<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Authorization\Models;

use App\Core\Foundation\Models\BaseModel;
use App\Domains\Identity\Authorization\Models\Permission;
use App\Domains\Identity\Authorization\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Tests\TestCase;

final class PermissionTest extends TestCase
{
    public function test_permission_extends_base_model(): void
    {
        $permission = new Permission();

        $this->assertInstanceOf(BaseModel::class, $permission);
    }

    public function test_permission_uses_permissions_table(): void
    {
        $permission = new Permission();

        $this->assertSame('permissions', $permission->getTable());
    }

    public function test_permission_uses_ulid_primary_key(): void
    {
        $permission = new Permission();

        $this->assertSame('string', $permission->getKeyType());
        $this->assertFalse($permission->getIncrementing());
    }

    public function test_permission_uses_timestamps(): void
    {
        $permission = new Permission();

        $this->assertTrue($permission->usesTimestamps());
    }

    public function test_permission_uses_soft_deletes(): void
    {
        $permission = new Permission();

        $this->assertArrayHasKey(
            SoftDeletes::class,
            class_uses_recursive($permission),
        );
    }

    public function test_permission_casts_is_active_to_boolean(): void
    {
        $permission = new Permission([
            'name' => 'View products',
            'slug' => 'products.view',
            'is_active' => true,
        ]);

        $this->assertIsBool($permission->is_active);
        $this->assertTrue($permission->is_active);
    }

    public function test_permission_defines_roles_relationship(): void
    {
        $permission = new Permission();

        $this->assertInstanceOf(
            BelongsToMany::class,
            $permission->roles(),
        );

        $this->assertSame(
            Role::class,
            $permission->roles()->getRelated()::class,
        );
    }
}
