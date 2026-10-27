<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Products\Http\Controllers;

use App\Domains\Categories\Models\Category;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Products\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

final class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    private Cooperative $cooperative;

    private Category $category;

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

        // Isole le comportement HTTP du contrôleur.
        // Les autorisations réelles nécessitent des tests distincts.
        Gate::before(static fn (): bool => true);

        $this->category = Category::factory()
            ->forCooperative($this->cooperative)
            ->create([
                'is_active' => true,
            ]);
    }

    public function test_product_index_returns_successful_response(): void
    {
        $response = $this->get(route(
            'backoffice.products.index',
            ['cooperative' => $this->cooperative->getKey()],
        ));

        $response->assertOk()
            ->assertViewIs('backoffice.products.index');
    }

    public function test_product_can_be_created_with_a_category(): void
    {
        $response = $this->post(route(
            'backoffice.products.store',
            ['cooperative' => $this->cooperative->getKey()],
        ), [
            'name' => 'Miel de montagne',
            'description' => 'Miel naturel',
            'is_active' => true,
            'category_ids' => [
                $this->category->getKey(),
            ],
            'catalog_ids' => [],
        ]);

        $product = Product::query()
            ->where('cooperative_id', $this->cooperative->getKey())
            ->where('name', 'Miel de montagne')
            ->firstOrFail();

        $response->assertRedirectToRoute(
            'backoffice.products.show',
            [
                'cooperative' => $this->cooperative->getKey(),
                'product' => $product->getKey(),
            ],
        );

        $this->assertDatabaseHas('products', [
            'id' => $product->getKey(),
            'cooperative_id' => $this->cooperative->getKey(),
            'name' => 'Miel de montagne',
            'slug' => 'miel-de-montagne',
            'is_active' => true,
            'deleted_at' => null,
        ]);

        $this->assertDatabaseHas('category_product', [
            'category_id' => $this->category->getKey(),
            'product_id' => $product->getKey(),
        ]);
    }

    public function test_product_creation_requires_a_name(): void
    {
        $response = $this->from(route(
            'backoffice.products.create',
            ['cooperative' => $this->cooperative->getKey()],
        ))->post(route(
            'backoffice.products.store',
            ['cooperative' => $this->cooperative->getKey()],
        ), [
            'description' => 'Produit sans nom',
            'category_ids' => [$this->category->getKey()],
        ]);

        $response->assertRedirect()
            ->assertSessionHasErrors(['name']);

        $this->assertDatabaseMissing('products', [
            'cooperative_id' => $this->cooperative->getKey(),
            'description' => 'Produit sans nom',
        ]);
    }

    public function test_product_can_be_updated(): void
    {
        $product = Product::factory()
            ->forCooperative($this->cooperative)
            ->create([
                'name' => 'Ancien produit',
                'slug' => 'ancien-produit',
                'is_active' => true,
            ]);

        $product->categories()->attach($this->category->getKey());

        $response = $this->patch(route(
            'backoffice.products.update',
            [
                'cooperative' => $this->cooperative->getKey(),
                'product' => $product->getKey(),
            ],
        ), [
            'name' => 'Produit modifié',
        ]);

        $response->assertRedirectToRoute(
            'backoffice.products.show',
            [
                'cooperative' => $this->cooperative->getKey(),
                'product' => $product->getKey(),
            ],
        );

        $this->assertDatabaseHas('products', [
            'id' => $product->getKey(),
            'name' => 'Produit modifié',
            'slug' => 'produit-modifie',
            'is_active' => true,
            'deleted_at' => null,
        ]);
    }

    public function test_product_can_be_deleted_logically(): void
    {
        $product = Product::factory()
            ->forCooperative($this->cooperative)
            ->create();

        $response = $this->delete(route(
            'backoffice.products.destroy',
            [
                'cooperative' => $this->cooperative->getKey(),
                'product' => $product->getKey(),
            ],
        ));

        $response->assertRedirectToRoute(
            'backoffice.products.index',
            ['cooperative' => $this->cooperative->getKey()],
        );

        $this->assertSoftDeleted('products', [
            'id' => $product->getKey(),
        ]);
    }

    public function test_inactive_product_can_be_reactivated(): void
    {
        $product = Product::factory()
            ->forCooperative($this->cooperative)
            ->inactive()
            ->create();

        $product->categories()->attach($this->category->getKey());

        $response = $this->patch(route(
            'backoffice.products.reactivate',
            [
                'cooperative' => $this->cooperative->getKey(),
                'product' => $product->getKey(),
            ],
        ));

        $response->assertRedirectToRoute(
            'backoffice.products.show',
            [
                'cooperative' => $this->cooperative->getKey(),
                'product' => $product->getKey(),
            ],
        );

        $this->assertDatabaseHas('products', [
            'id' => $product->getKey(),
            'is_active' => true,
            'deleted_at' => null,
        ]);
    }

    public function test_product_from_another_cooperative_is_not_found(): void
    {
        $otherCooperative = Cooperative::factory()
            ->active()
            ->create();

        $foreignProduct = Product::factory()
            ->forCooperative($otherCooperative)
            ->create();

        $response = $this->get(route(
            'backoffice.products.show',
            [
                'cooperative' => $this->cooperative->getKey(),
                'product' => $foreignProduct->getKey(),
            ],
        ));

        $response->assertNotFound();
    }
}