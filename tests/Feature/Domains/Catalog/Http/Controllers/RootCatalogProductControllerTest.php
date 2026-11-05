<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Catalog\Http\Controllers;

use App\Domains\Catalog\Models\Catalog;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authentication\Http\Middleware\EnsureActiveAuthenticationSession;
use App\Domains\Identity\Authorization\Models\Permission;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Products\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RootCatalogProductControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(EnsureActiveAuthenticationSession::class);
    }

    public function test_root_can_add_active_product_of_selected_cooperative_to_catalog(): void
    {
        $cooperative = Cooperative::factory()->active()->create();
        $catalog = Catalog::factory()->create(['cooperative_id' => $cooperative->getKey(), 'is_active' => true]);
        $product = Product::factory()->forCooperative($cooperative)->create(['is_active' => true]);
        $actor = $this->actor('super_admin_root', ['cooperatives.view', 'catalogs.update']);

        $this->actingAs($actor)
            ->post(route('root.backoffice.cooperatives.catalogs.products.store', [
                'cooperative' => $cooperative->getKey(), 'catalog' => $catalog->getKey(),
            ]), ['product_id' => $product->getKey()])
            ->assertRedirect(route('root.backoffice.cooperatives.catalogs.show', [
                'cooperative' => $cooperative->getKey(), 'catalog' => $catalog->getKey(),
            ]));

        $this->assertDatabaseHas('catalog_product', [
            'catalog_id' => $catalog->getKey(), 'product_id' => $product->getKey(),
        ]);
    }

    public function test_root_cannot_add_product_from_another_cooperative(): void
    {
        $cooperative = Cooperative::factory()->active()->create();
        $other = Cooperative::factory()->active()->create();
        $catalog = Catalog::factory()->create(['cooperative_id' => $cooperative->getKey(), 'is_active' => true]);
        $foreignProduct = Product::factory()->forCooperative($other)->create(['is_active' => true]);
        $actor = $this->actor('super_admin_root', ['cooperatives.view', 'catalogs.update']);

        $this->actingAs($actor)
            ->from(route('root.backoffice.cooperatives.catalogs.show', [
                'cooperative' => $cooperative->getKey(), 'catalog' => $catalog->getKey(),
            ]))
            ->post(route('root.backoffice.cooperatives.catalogs.products.store', [
                'cooperative' => $cooperative->getKey(), 'catalog' => $catalog->getKey(),
            ]), ['product_id' => $foreignProduct->getKey()])
            ->assertRedirect()
            ->assertSessionHasErrors(['product_id']);

        $this->assertDatabaseMissing('catalog_product', [
            'catalog_id' => $catalog->getKey(), 'product_id' => $foreignProduct->getKey(),
        ]);
    }

    public function test_root_can_remove_product_without_deleting_it(): void
    {
        $cooperative = Cooperative::factory()->active()->create();
        $catalog = Catalog::factory()->create(['cooperative_id' => $cooperative->getKey()]);
        $product = Product::factory()->forCooperative($cooperative)->create();
        $catalog->products()->attach($product->getKey());
        $actor = $this->actor('super_admin_root', ['cooperatives.view', 'catalogs.update']);

        $this->actingAs($actor)
            ->delete(route('root.backoffice.cooperatives.catalogs.products.destroy', [
                'cooperative' => $cooperative->getKey(),
                'catalog' => $catalog->getKey(),
                'product' => $product->getKey(),
            ]))
            ->assertRedirect(route('root.backoffice.cooperatives.catalogs.show', [
                'cooperative' => $cooperative->getKey(), 'catalog' => $catalog->getKey(),
            ]));

        $this->assertDatabaseMissing('catalog_product', [
            'catalog_id' => $catalog->getKey(), 'product_id' => $product->getKey(),
        ]);
        $this->assertDatabaseHas('products', [
            'id' => $product->getKey(), 'deleted_at' => null,
        ]);
    }

    public function test_non_root_cannot_use_root_catalog_product_routes(): void
    {
        $cooperative = Cooperative::factory()->active()->create();
        $catalog = Catalog::factory()->create(['cooperative_id' => $cooperative->getKey()]);
        $actor = $this->actor('super_admin', ['cooperatives.view', 'catalogs.update']);
        $product = Product::factory()->forCooperative($cooperative)->create();

        $this->actingAs($actor)
            ->post(route('root.backoffice.cooperatives.catalogs.products.store', [
                'cooperative' => $cooperative->getKey(), 'catalog' => $catalog->getKey(),
            ]), ['product_id' => $product->getKey()])
            ->assertForbidden();
    }

    private function actor(string $roleSlug, array $permissions): User
    {
        $actor = User::factory()->create();
        $role = Role::query()->firstOrCreate(
            ['slug' => $roleSlug],
            ['name' => str($roleSlug)->replace('_', ' ')->title()->toString(), 'is_active' => true],
        );

        foreach ($permissions as $slug) {
            $permission = Permission::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => str($slug)->replace('.', ' ')->title()->toString(), 'is_active' => true],
            );
            $role->permissions()->syncWithoutDetaching([$permission->getKey()]);
        }

        $actor->roles()->syncWithoutDetaching([$role->getKey()]);

        return $actor;
    }
}
