<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Authorization\Models;

use App\Core\Foundation\Models\BaseModel;
use App\Domains\Identity\Authorization\Models\Permission;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Tests\TestCase;

final class RoleTest extends TestCase
{
    public function test_role_extends_base_model(): void
    {
        $role = new Role();

        $this->assertInstanceOf(BaseModel::class, $role);
    }

    public function test_role_uses_roles_table(): void
    {
        $role = new Role();

        $this->assertSame('roles', $role->getTable());
    }

    public function test_role_uses_ulid_primary_key(): void
    {
        $role = new Role();

        $this->assertSame('string', $role->getKeyType());
        $this->assertFalse($role->getIncrementing());
    }

    public function test_role_uses_timestamps(): void
    {
        $role = new Role();

        $this->assertTrue($role->usesTimestamps());
    }

    public function test_role_uses_soft_deletes(): void
    {
        $role = new Role();

        $this->assertArrayHasKey(
            SoftDeletes::class,
            class_uses_recursive($role),
        );
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

    public function test_role_defines_permissions_relationship(): void
    {
        $role = new Role();

        $this->assertInstanceOf(
            BelongsToMany::class,
            $role->permissions(),
        );

        $this->assertSame(
            Permission::class,
            $role->permissions()->getRelated()::class,
        );
    }

    public function test_role_defines_users_relationship(): void
    {
        $role = new Role();

        $this->assertInstanceOf(
            BelongsToMany::class,
            $role->users(),
        );

        $this->assertSame(
            User::class,
            $role->users()->getRelated()::class,
        );
    }
}
