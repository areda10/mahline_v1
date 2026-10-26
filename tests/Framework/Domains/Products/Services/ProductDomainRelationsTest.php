<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Products\Services;

use App\Domains\Catalog\Models\Catalog;
use App\Domains\Catalog\Services\CatalogService;
use App\Domains\Categories\Models\Category;
use App\Domains\Categories\Services\CategoryService;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Products\Models\Product;
use App\Domains\Products\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ProductDomainRelationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_can_be_linked_to_global_and_local_categories_and_its_cooperative_catalog(): void
    {
        $cooperative = Cooperative::factory()
            ->active()
            ->create();

        $globalCategory = Category::factory()
            ->global()
            ->create();

        $localCategory = Category::factory()
            ->forCooperative($cooperative)
            ->create();

        $catalog = Catalog::factory()->create([
            'cooperative_id' => $cooperative->getKey(),
            'is_active' => true,
        ]);

        $product = app(ProductService::class)->create(
            (string) $cooperative->getKey(),
            [
                'name' => 'Produit de test',
                'category_ids' => [
                    $globalCategory->getKey(),
                    $localCategory->getKey(),
                ],
                'catalog_ids' => [$catalog->getKey()],
            ],
        );

        $this->assertDatabaseHas('category_product', [
            'category_id' => $globalCategory->getKey(),
            'product_id' => $product->getKey(),
        ]);

        $this->assertDatabaseHas('category_product', [
            'category_id' => $localCategory->getKey(),
            'product_id' => $product->getKey(),
        ]);

        $this->assertDatabaseHas('catalog_product', [
            'catalog_id' => $catalog->getKey(),
            'product_id' => $product->getKey(),
        ]);

        $this->assertSame(
            (string) $cooperative->getKey(),
            (string) $product->cooperative_id,
        );
    }

    public function test_product_cannot_be_associated_with_another_cooperatives_local_category(): void
    {
        $cooperativeA = Cooperative::factory()->active()->create();
        $cooperativeB = Cooperative::factory()->active()->create();

        $foreignCategory = Category::factory()
            ->forCooperative($cooperativeB)
            ->create();

        try {
            app(ProductService::class)->create(
                (string) $cooperativeA->getKey(),
                [
                    'name' => 'Produit interdit',
                    'category_ids' => [$foreignCategory->getKey()],
                ],
            );

            $this->fail('Une ValidationException était attendue.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'category_ids',
                $exception->errors(),
            );
        }

        $this->assertDatabaseMissing('products', [
            'cooperative_id' => $cooperativeA->getKey(),
            'name' => 'Produit interdit',
        ]);
    }

    public function test_product_cannot_be_associated_with_another_cooperatives_catalog(): void
    {
        $cooperativeA = Cooperative::factory()->active()->create();
        $cooperativeB = Cooperative::factory()->active()->create();

        $foreignCatalog = Catalog::factory()->create([
            'cooperative_id' => $cooperativeB->getKey(),
            'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);

        app(ProductService::class)->create(
            (string) $cooperativeA->getKey(),
            [
                'name' => 'Produit interdit',
                'catalog_ids' => [$foreignCatalog->getKey()],
            ],
        );
    }

    public function test_catalog_service_cannot_add_a_product_from_another_cooperative(): void
    {
        $cooperativeA = Cooperative::factory()->active()->create();
        $cooperativeB = Cooperative::factory()->active()->create();

        $catalogA = Catalog::factory()->create([
            'cooperative_id' => $cooperativeA->getKey(),
            'is_active' => true,
        ]);

        $productB = Product::factory()
            ->forCooperative($cooperativeB)
            ->create();

        $this->expectException(ValidationException::class);

        app(CatalogService::class)->addProduct(
            $catalogA,
            (string) $cooperativeA->getKey(),
            (string) $productB->getKey(),
        );
    }

    public function test_deleting_a_local_category_preserves_the_product_but_deactivates_it_when_no_active_category_remains(): void
    {
        $cooperative = Cooperative::factory()->active()->create();

        $category = Category::factory()
            ->forCooperative($cooperative)
            ->create();

        $product = Product::factory()
            ->forCooperative($cooperative)
            ->create([
                'is_active' => true,
            ]);

        $product->categories()->attach($category->getKey());

        app(CategoryService::class)->delete($category);

        $this->assertSoftDeleted('categories', [
            'id' => $category->getKey(),
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->getKey(),
            'is_active' => false,
            'deleted_at' => null,
        ]);

        $this->assertDatabaseMissing('category_product', [
            'category_id' => $category->getKey(),
            'product_id' => $product->getKey(),
        ]);
    }

    public function test_deleting_a_category_does_not_deactivate_a_product_that_still_has_another_active_category(): void
    {
        $cooperative = Cooperative::factory()->active()->create();

        $categoryToDelete = Category::factory()
            ->forCooperative($cooperative)
            ->create();

        $categoryToKeep = Category::factory()
            ->forCooperative($cooperative)
            ->create();

        $product = Product::factory()
            ->forCooperative($cooperative)
            ->create([
                'is_active' => true,
            ]);

        $product->categories()->attach([
            $categoryToDelete->getKey(),
            $categoryToKeep->getKey(),
        ]);

        app(CategoryService::class)->delete($categoryToDelete);

        $this->assertDatabaseHas('products', [
            'id' => $product->getKey(),
            'is_active' => true,
            'deleted_at' => null,
        ]);

        $this->assertTrue(
            $product->fresh()->categories()
                ->whereKey($categoryToKeep->getKey())
                ->exists(),
        );
    }

    public function test_deleting_a_global_category_preserves_local_children_as_roots(): void
    {
        $cooperative = Cooperative::factory()->active()->create();

        $globalCategory = Category::factory()
            ->global()
            ->create();

        $localChild = Category::factory()
            ->forCooperative($cooperative)
            ->childOf($globalCategory)
            ->create();

        app(CategoryService::class)->delete($globalCategory);

        $this->assertSoftDeleted('categories', [
            'id' => $globalCategory->getKey(),
        ]);

        $this->assertDatabaseHas('categories', [
            'id' => $localChild->getKey(),
            'cooperative_id' => $cooperative->getKey(),
            'parent_id' => null,
            'deleted_at' => null,
        ]);
    }
}
