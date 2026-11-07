<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Catalog\Policies;

use App\Domains\Catalog\Models\Catalog;
use App\Domains\Catalog\Policies\CatalogPolicy;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authorization\Models\Permission;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CatalogPolicyTest extends TestCase
{
    use RefreshDatabase;

    private CatalogPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new CatalogPolicy();
    }

    public function test_view_any_requires_catalog_view_permission(): void
    {
        $user = $this->userWithPermission('catalogs.view');

        $this->assertTrue($this->policy->viewAny($user));
    }

    public function test_view_any_is_denied_without_permission(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($this->policy->viewAny($user));
    }

    public function test_create_requires_permission_and_cooperative_access(): void
    {
        $cooperative = Cooperative::factory()->create();

        $user = $this->userWithPermission(
            'catalogs.create',
            'cooperative_admin',
            (string) $cooperative->getKey(),
        );

        $this->assertTrue(
            $this->policy->create(
                $user,
                (string) $cooperative->getKey(),
            )
        );
    }

    public function test_create_is_denied_for_another_cooperative(): void
    {
        $owner = Cooperative::factory()->create();
        $other = Cooperative::factory()->create();

        $user = $this->userWithPermission(
            'catalogs.create',
            'cooperative_admin',
            (string) $owner->getKey(),
        );

        $this->assertFalse(
            $this->policy->create(
                $user,
                (string) $other->getKey(),
            )
        );
    }

    public function test_update_is_denied_for_another_cooperative(): void
    {
        $owner = Cooperative::factory()->create();
        $other = Cooperative::factory()->create();

        $catalog = Catalog::factory()->create([
            'cooperative_id' => $owner->getKey(),
        ]);

        $user = $this->userWithPermission(
            'catalogs.update',
            'cooperative_admin',
            (string) $other->getKey(),
        );

        $this->assertFalse($this->policy->update($user, $catalog));
    }

    public function test_root_can_update_catalog_from_any_cooperative(): void
    {
        $cooperative = Cooperative::factory()->create();
        $catalog = Catalog::factory()->create(['cooperative_id' => $cooperative->getKey()]);
        $root = $this->userWithPermission('catalogs.update', 'super_admin_root');

        $this->assertTrue($this->policy->update($root, $catalog));
    }

    public function test_super_admin_can_update_catalog_of_assigned_cooperative(): void
    {
        $cooperative = Cooperative::factory()->create();
        $catalog = Catalog::factory()->create(['cooperative_id' => $cooperative->getKey()]);
        $admin = $this->userWithPermission('catalogs.update', 'super_admin');
        $admin->cooperatives()->attach($cooperative->getKey());

        $this->assertTrue($this->policy->update($admin, $catalog));
    }

    public function test_super_admin_cannot_update_catalog_of_unassigned_cooperative(): void
    {
        $cooperative = Cooperative::factory()->create();
        $catalog = Catalog::factory()->create(['cooperative_id' => $cooperative->getKey()]);
        $admin = $this->userWithPermission('catalogs.update', 'super_admin');

        $this->assertFalse($this->policy->update($admin, $catalog));
    }

    public function test_cooperative_admin_can_update_catalog_of_own_cooperative(): void
    {
        $cooperative = Cooperative::factory()->create();
        $catalog = Catalog::factory()->create(['cooperative_id' => $cooperative->getKey()]);
        $admin = $this->userWithPermission(
            'catalogs.update',
            'cooperative_admin',
            (string) $cooperative->getKey(),
        );

        $this->assertTrue($this->policy->update($admin, $catalog));
    }

    private function userWithPermission(
        string $permissionSlug,
        string $roleName = 'test-role',
        ?string $cooperativeId = null,
    ): User {
        $user = User::factory()->create([
            'status' => UserStatus::Active,
            'cooperative_id' => $cooperativeId,
        ]);

        $roleSlug = $roleName;

        $role = Role::query()->create([
            'name' => $roleName,
            'slug' => $roleSlug,
            'is_active' => true,
        ]);

        $permission = Permission::query()->firstOrCreate(
            ['slug' => $permissionSlug],
            [
                'name' => $permissionSlug,
                'is_active' => true,
            ],
        );

        $role->permissions()->attach($permission->getKey());
        $user->roles()->attach($role->getKey());

        return $user;
    }
}
