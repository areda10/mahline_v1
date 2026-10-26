<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Authorization;

use App\Domains\Catalog\Models\Catalog;
use App\Domains\Catalog\Policies\CatalogPolicy;
use App\Domains\Categories\Models\Category;
use App\Domains\Categories\Policies\CategoryPolicy;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authorization\Models\Permission;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Products\Models\Product;
use App\Domains\Products\Policies\ProductPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProductCatalogCategoryPolicyTest extends TestCase
{
    use RefreshDatabase;

    private Cooperative $cooperativeA;
    private Cooperative $cooperativeB;

    private array $permissions = [
        'cooperatives.view',
        'products.view',
        'products.update',
        'categories.view',
        'categories.update',
        'catalogs.view',
        'catalogs.update',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->cooperativeA = Cooperative::factory()
            ->active()
            ->create();

        $this->cooperativeB = Cooperative::factory()
            ->active()
            ->create();
    }

    public function test_user_and_cooperative_admin_are_limited_to_their_own_cooperative(): void
    {
        $productA = Product::factory()
            ->forCooperative($this->cooperativeA)
            ->create();

        $productB = Product::factory()
            ->forCooperative($this->cooperativeB)
            ->create();

        $categoryA = Category::factory()
            ->forCooperative($this->cooperativeA)
            ->create();

        $categoryB = Category::factory()
            ->forCooperative($this->cooperativeB)
            ->create();

        $catalogA = Catalog::factory()->create([
            'cooperative_id' => $this->cooperativeA->getKey(),
        ]);

        $catalogB = Catalog::factory()->create([
            'cooperative_id' => $this->cooperativeB->getKey(),
        ]);

        foreach (['user', 'cooperative_admin'] as $roleSlug) {
            $user = $this->makeUser(
                $roleSlug,
                $this->cooperativeA,
            );

            $this->assertTrue(
                app(ProductPolicy::class)->update($user, $productA),
                "{$roleSlug} doit pouvoir modifier un produit de sa coopérative.",
            );

            $this->assertFalse(
                app(ProductPolicy::class)->update($user, $productB),
                "{$roleSlug} ne doit pas modifier un produit étranger.",
            );

            $this->assertTrue(
                app(CategoryPolicy::class)->update($user, $categoryA),
            );

            $this->assertFalse(
                app(CategoryPolicy::class)->update($user, $categoryB),
            );

            $this->assertTrue(
                app(CatalogPolicy::class)->update($user, $catalogA),
            );

            $this->assertFalse(
                app(CatalogPolicy::class)->update($user, $catalogB),
            );
        }
    }

    public function test_super_admin_can_access_only_assigned_cooperatives(): void
    {
        $user = $this->makeUser(
            'super_admin',
            null,
            [$this->cooperativeA],
        );

        $productA = Product::factory()
            ->forCooperative($this->cooperativeA)
            ->create();

        $productB = Product::factory()
            ->forCooperative($this->cooperativeB)
            ->create();

        $this->assertTrue(
            app(ProductPolicy::class)->update($user, $productA),
        );

        $this->assertFalse(
            app(ProductPolicy::class)->update($user, $productB),
        );

        $catalogB = Catalog::factory()->create([
            'cooperative_id' => $this->cooperativeB->getKey(),
        ]);

        $this->assertFalse(
            app(CatalogPolicy::class)->update($user, $catalogB),
        );

        $globalCategory = Category::factory()->global()->create();

        $this->assertFalse(
            app(CategoryPolicy::class)->update($user, $globalCategory),
            'Un super_admin ne doit pas administrer les catégories globales.',
        );
    }

    public function test_super_admin_root_can_access_all_cooperatives_and_manage_global_categories(): void
    {
        $root = $this->makeUser('super_admin_root');

        $productB = Product::factory()
            ->forCooperative($this->cooperativeB)
            ->create();

        $catalogB = Catalog::factory()->create([
            'cooperative_id' => $this->cooperativeB->getKey(),
        ]);

        $localCategoryB = Category::factory()
            ->forCooperative($this->cooperativeB)
            ->create();

        $globalCategory = Category::factory()
            ->global()
            ->create();

        $this->assertTrue(
            app(ProductPolicy::class)->update($root, $productB),
        );

        $this->assertTrue(
            app(CatalogPolicy::class)->update($root, $catalogB),
        );

        $this->assertTrue(
            app(CategoryPolicy::class)->update($root, $localCategoryB),
        );

        $this->assertTrue(
            app(CategoryPolicy::class)->update($root, $globalCategory),
        );
    }

    public function test_root_role_does_not_bypass_a_missing_permission(): void
    {
        $root = $this->makeUser(
            'super_admin_root',
            null,
            [],
            ['cooperatives.view'],
        );

        $product = Product::factory()
            ->forCooperative($this->cooperativeB)
            ->create();

        $this->assertFalse(
            app(ProductPolicy::class)->update($root, $product),
        );
    }

    public function test_global_category_is_readable_but_not_manageable_by_a_cooperative_user(): void
    {
        $user = $this->makeUser(
            'cooperative_admin',
            $this->cooperativeA,
        );

        $globalCategory = Category::factory()->global()->create();

        $policy = app(CategoryPolicy::class);

        $this->assertTrue(
            $policy->view($user, $globalCategory),
        );

        $this->assertFalse(
            $policy->update($user, $globalCategory),
        );

        $this->assertFalse(
            $policy->delete($user, $globalCategory),
        );
    }

    private function makeUser(
        string $roleSlug,
        ?Cooperative $cooperative = null,
        array $assignedCooperatives = [],
        ?array $permissions = null,
    ): User {
        $user = User::factory()->create([
            'cooperative_id' => $cooperative?->getKey(),
        ]);

        $role = Role::query()->firstOrCreate(
            ['slug' => $roleSlug],
            [
                'name' => str_replace('_', ' ', ucfirst($roleSlug)),
                'is_active' => true,
            ],
        );

        $permissions ??= $this->permissions;

        foreach ($permissions as $permissionSlug) {
            $permission = Permission::query()->firstOrCreate(
                ['slug' => $permissionSlug],
                [
                    'name' => $permissionSlug,
                    'is_active' => true,
                ],
            );

            $role->permissions()->syncWithoutDetaching([
                $permission->getKey(),
            ]);
        }

        $user->roles()->attach($role->getKey());

        foreach ($assignedCooperatives as $assignedCooperative) {
            $user->cooperatives()->attach(
                $assignedCooperative->getKey(),
            );
        }

        return $user;
    }
}
