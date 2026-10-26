<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Products\Security;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authorization\Models\Permission;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Products\Models\Product;
use App\Domains\Products\Policies\ProductPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProductAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private ProductPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = app(ProductPolicy::class);
    }

    private function createUserWithRoleAndPermission(
        string $cooperativeId,
        string $permissionSlug,
    ): User {
        $user = User::factory()->create([
            'cooperative_id' => $cooperativeId,
        ]);

        $role = Role::query()->create([
            'name' => 'User test',
            'slug' => 'user',
            'is_active' => true,
        ]);

        $permission = Permission::query()->create([
            'name' => $permissionSlug,
            'slug' => $permissionSlug,
            'is_active' => true,
        ]);

        $role->permissions()->attach($permission->getKey());
        $user->roles()->attach($role->getKey());

        return $user;
    }

    private function createUserWithRoleWithoutPermission(
        string $cooperativeId,
    ): User {
        $user = User::factory()->create([
            'cooperative_id' => $cooperativeId,
        ]);

        $role = Role::query()->create([
            'name' => 'User test sans permission',
            'slug' => 'user',
            'is_active' => true,
        ]);

        $user->roles()->attach($role->getKey());

        return $user;
    }

    public function test_user_from_another_cooperative_cannot_view_product(): void
    {
        $productCooperative = Cooperative::factory()->create();
        $otherCooperative = Cooperative::factory()->create();

        $product = Product::factory()->create([
            'cooperative_id' => $productCooperative->getKey(),
        ]);

        $user = $this->createUserWithRoleAndPermission(
            (string) $otherCooperative->getKey(),
            'products.view',
        );

        $this->assertFalse(
            $this->policy->view($user, $product),
        );
    }

    public function test_user_without_view_permission_cannot_view_product(): void
    {
        $cooperative = Cooperative::factory()->create();

        $product = Product::factory()->create([
            'cooperative_id' => $cooperative->getKey(),
        ]);

        $user = $this->createUserWithRoleWithoutPermission(
            (string) $cooperative->getKey(),
        );

        $this->assertFalse(
            $this->policy->view($user, $product),
        );
    }

    public function test_user_from_another_cooperative_cannot_update_product(): void
    {
        $productCooperative = Cooperative::factory()->create();
        $otherCooperative = Cooperative::factory()->create();

        $product = Product::factory()->create([
            'cooperative_id' => $productCooperative->getKey(),
        ]);

        $user = $this->createUserWithRoleAndPermission(
            (string) $otherCooperative->getKey(),
            'products.update',
        );

        $this->assertFalse(
            $this->policy->update($user, $product),
        );
    }

    public function test_user_from_another_cooperative_cannot_delete_product(): void
    {
        $productCooperative = Cooperative::factory()->create();
        $otherCooperative = Cooperative::factory()->create();

        $product = Product::factory()->create([
            'cooperative_id' => $productCooperative->getKey(),
        ]);

        $user = $this->createUserWithRoleAndPermission(
            (string) $otherCooperative->getKey(),
            'products.delete',
        );

        $this->assertFalse(
            $this->policy->delete($user, $product),
        );
    }
}
