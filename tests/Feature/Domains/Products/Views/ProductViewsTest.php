<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Products\Views;

use App\Domains\Catalog\Models\Catalog;
use App\Domains\Categories\Models\Category;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Products\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

final class ProductViewsTest extends TestCase
{
    use RefreshDatabase;

    private Cooperative $cooperative;

    private Category $category;

    private Catalog $catalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cooperative = Cooperative::factory()
            ->active()
            ->create();

        $this->actingAs(User::factory()->create([
            'cooperative_id' => $this->cooperative->getKey(),
        ]));

        Gate::before(static fn (): bool => true);

        $this->category = Category::factory()
            ->forCooperative($this->cooperative)
            ->create(['name' => 'Catégorie de test']);

        $this->catalog = Catalog::factory()->create([
            'cooperative_id' => $this->cooperative->getKey(),
            'name' => 'Catalogue de test',
        ]);
    }

    public function test_product_index_view_renders(): void
    {
        $product = Product::factory()
            ->forCooperative($this->cooperative)
            ->create(['name' => 'Produit de test']);

        $product->categories()->attach($this->category->getKey());
        $product->catalogs()->attach($this->catalog->getKey());

        $this->get(route('backoffice.products.index', [
            'cooperative' => $this->cooperative->getKey(),
        ]))
            ->assertOk()
            ->assertViewIs('backoffice.products.index')
            ->assertSee('Produit de test')
            ->assertSee('Catégorie de test')
            ->assertSee('Catalogue de test');
    }

    public function test_product_create_view_renders_category_and_catalog_selectors(): void
    {
        $this->get(route('backoffice.products.create', [
            'cooperative' => $this->cooperative->getKey(),
        ]))
            ->assertOk()
            ->assertViewIs('backoffice.products.create')
            ->assertSee('Catégorie de test')
            ->assertSee('Catalogue de test')
            ->assertSee('category_ids[]')
            ->assertSee('catalog_ids[]');
    }

    public function test_product_edit_view_renders_existing_values(): void
    {
        $product = Product::factory()
            ->forCooperative($this->cooperative)
            ->create(['name' => 'Produit à modifier']);

        $product->categories()->attach($this->category->getKey());
        $product->catalogs()->attach($this->catalog->getKey());

        $this->get(route('backoffice.products.edit', [
            'cooperative' => $this->cooperative->getKey(),
            'product' => $product->getKey(),
        ]))
            ->assertOk()
            ->assertViewIs('backoffice.products.edit')
            ->assertSee('Produit à modifier')
            ->assertSee('Catégorie de test')
            ->assertSee('Catalogue de test')
            ->assertSee('Enregistrer');
    }

    public function test_product_show_view_renders_details_and_relations(): void
    {
        $product = Product::factory()
            ->forCooperative($this->cooperative)
            ->create(['name' => 'Produit détaillé']);

        $product->categories()->attach($this->category->getKey());
        $product->catalogs()->attach($this->catalog->getKey());

        $this->get(route('backoffice.products.show', [
            'cooperative' => $this->cooperative->getKey(),
            'product' => $product->getKey(),
        ]))
            ->assertOk()
            ->assertViewIs('backoffice.products.show')
            ->assertSee('Produit détaillé')
            ->assertSee('Catégorie de test')
            ->assertSee('Catalogue de test');
    }
}