<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Catalog\Http\Controllers;

use App\Domains\Catalog\Models\Catalog;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Products\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

final class CatalogProductControllerTest extends TestCase
{
    use RefreshDatabase;

    private Cooperative $cooperative;

    private Catalog $catalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cooperative = Cooperative::factory()
            ->active()
            ->create();

        $user = User::factory()->create([
            'cooperative_id' => $this->cooperative->getKey(),
        ]);

        $this->actingAs($user);

        Gate::before(static fn (): bool => true);

        $this->catalog = Catalog::factory()->create([
            'cooperative_id' => $this->cooperative->getKey(),
            'is_active' => true,
        ]);
    }

    public function test_product_can_be_added_to_its_cooperatives_catalog(): void
    {
        $product = Product::factory()
            ->forCooperative($this->cooperative)
            ->create([
                'is_active' => true,
            ]);

        $response = $this->post(route(
            'backoffice.catalogs.products.store',
            [
                'cooperative' => $this->cooperative->getKey(),
                'catalog' => $this->catalog->getKey(),
            ],
        ), [
            'product_id' => $product->getKey(),
        ]);

        $response->assertRedirectToRoute(
            'backoffice.catalogs.show',
            [
                'cooperative' => $this->cooperative->getKey(),
                'catalog' => $this->catalog->getKey(),
            ],
        );

        $this->assertDatabaseHas('catalog_product', [
            'catalog_id' => $this->catalog->getKey(),
            'product_id' => $product->getKey(),
        ]);
    }

    public function test_product_from_another_cooperative_cannot_be_added(): void
    {
        $otherCooperative = Cooperative::factory()
            ->active()
            ->create();

        $foreignProduct = Product::factory()
            ->forCooperative($otherCooperative)
            ->create([
                'is_active' => true,
            ]);

        $response = $this->from(route(
            'backoffice.catalogs.show',
            [
                'cooperative' => $this->cooperative->getKey(),
                'catalog' => $this->catalog->getKey(),
            ],
        ))->post(route(
            'backoffice.catalogs.products.store',
            [
                'cooperative' => $this->cooperative->getKey(),
                'catalog' => $this->catalog->getKey(),
            ],
        ), [
            'product_id' => $foreignProduct->getKey(),
        ]);

        $response->assertRedirect()
            ->assertSessionHasErrors(['product_id']);

        $this->assertDatabaseMissing('catalog_product', [
            'catalog_id' => $this->catalog->getKey(),
            'product_id' => $foreignProduct->getKey(),
        ]);
    }

    public function test_product_can_be_removed_from_catalog_without_deleting_product(): void
    {
        $product = Product::factory()
            ->forCooperative($this->cooperative)
            ->create();

        $this->catalog->products()->attach($product->getKey());

        $response = $this->delete(route(
            'backoffice.catalogs.products.destroy',
            [
                'cooperative' => $this->cooperative->getKey(),
                'catalog' => $this->catalog->getKey(),
                'product' => $product->getKey(),
            ],
        ));

        $response->assertRedirectToRoute(
            'backoffice.catalogs.show',
            [
                'cooperative' => $this->cooperative->getKey(),
                'catalog' => $this->catalog->getKey(),
            ],
        );

        $this->assertDatabaseMissing('catalog_product', [
            'catalog_id' => $this->catalog->getKey(),
            'product_id' => $product->getKey(),
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->getKey(),
            'deleted_at' => null,
        ]);
    }

    public function test_missing_product_id_is_rejected(): void
    {
        $response = $this->from(route(
            'backoffice.catalogs.show',
            [
                'cooperative' => $this->cooperative->getKey(),
                'catalog' => $this->catalog->getKey(),
            ],
        ))->post(route(
            'backoffice.catalogs.products.store',
            [
                'cooperative' => $this->cooperative->getKey(),
                'catalog' => $this->catalog->getKey(),
            ],
        ), []);

        $response->assertRedirect()
            ->assertSessionHasErrors(['product_id']);
    }
}