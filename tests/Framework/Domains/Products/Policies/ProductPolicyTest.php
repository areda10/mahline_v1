<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Products\Policies;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authorization\Models\Permission;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Products\Models\Product;
use App\Domains\Products\Policies\ProductPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProductPolicyTest extends TestCase
{
    use RefreshDatabase;

    private ProductPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new ProductPolicy;
    }

    public function test_root_can_access_product_from_any_cooperative(): void
    {
        $cooperative = Cooperative::factory()->create();
        $product = Product::factory()->forCooperative($cooperative)->create();
        $root = $this->userWithPermission('products.update', 'super_admin_root');

        $this->assertTrue($this->policy->update($root, $product));
    }

    public function test_super_admin_can_access_product_of_assigned_cooperative(): void
    {
        $cooperative = Cooperative::factory()->create();
        $product = Product::factory()->forCooperative($cooperative)->create();
        $admin = $this->userWithPermission('products.update', 'super_admin');
        $admin->cooperatives()->attach($cooperative->getKey());

        $this->assertTrue($this->policy->update($admin, $product));
    }

    public function test_super_admin_cannot_access_product_of_unassigned_cooperative(): void
    {
        $cooperative = Cooperative::factory()->create();
        $product = Product::factory()->forCooperative($cooperative)->create();
        $admin = $this->userWithPermission('products.update', 'super_admin');

        $this->assertFalse($this->policy->update($admin, $product));
    }

    public function test_cooperative_admin_can_access_product_of_own_cooperative(): void
    {
        $cooperative = Cooperative::factory()->create();
        $product = Product::factory()->forCooperative($cooperative)->create();
        $admin = $this->userWithPermission(
            'products.update',
            'cooperative_admin',
            (string) $cooperative->getKey(),
        );

        $this->assertTrue($this->policy->update($admin, $product));
    }

    public function test_cooperative_admin_cannot_access_product_of_another_cooperative(): void
    {
        $own = Cooperative::factory()->create();
        $other = Cooperative::factory()->create();
        $product = Product::factory()->forCooperative($other)->create();
        $admin = $this->userWithPermission(
            'products.update',
            'cooperative_admin',
            (string) $own->getKey(),
        );

        $this->assertFalse($this->policy->update($admin, $product));
    }

    public function test_access_is_denied_without_required_permission_even_for_root(): void
    {
        $cooperative = Cooperative::factory()->create();
        $product = Product::factory()->forCooperative($cooperative)->create();
        $root = $this->userWithPermission('products.view', 'super_admin_root');

        $this->assertFalse($this->policy->update($root, $product));
    }

    private function userWithPermission(
        string $permissionSlug,
        string $roleSlug,
        ?string $cooperativeId = null,
    ): User {
        $user = User::factory()->create([
            'status' => UserStatus::Active,
            'cooperative_id' => $cooperativeId,
        ]);

        $role = Role::query()->create([
            'name' => $roleSlug,
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
