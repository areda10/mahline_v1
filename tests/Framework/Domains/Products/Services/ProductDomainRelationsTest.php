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
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
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

    public function test_database_rejects_catalog_product_relation_across_cooperatives(): void
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

        try {
            DB::table('catalog_product')->insert([
                'catalog_id' => $catalogA->getKey(),
                'product_id' => $productB->getKey(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->fail(
                'MySQL aurait dû refuser cette association entre coopératives.'
            );
        } catch (QueryException $exception) {
            $this->assertStringContainsString(
                'catalog',
                strtolower($exception->getMessage())
            );
        }

        $this->assertDatabaseMissing('catalog_product', [
            'catalog_id' => $catalogA->getKey(),
            'product_id' => $productB->getKey(),
        ]);
    }

    public function test_database_rejects_foreign_local_category_for_product(): void
    {
        $cooperativeA = Cooperative::factory()->active()->create();
        $cooperativeB = Cooperative::factory()->active()->create();

        $productA = Product::factory()
            ->forCooperative($cooperativeA)
            ->create();

        $categoryB = Category::factory()
            ->forCooperative($cooperativeB)
            ->create();

        try {
            DB::table('category_product')->insert([
                'category_id' => $categoryB->getKey(),
                'product_id' => $productA->getKey(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->fail(
                'MySQL aurait dû refuser cette catégorie locale étrangère.'
            );
        } catch (QueryException $exception) {
            $this->assertStringContainsString(
                'categorie',
                strtolower($exception->getMessage())
            );
        }

        $this->assertDatabaseMissing('category_product', [
            'category_id' => $categoryB->getKey(),
            'product_id' => $productA->getKey(),
        ]);
    }

    public function test_database_allows_global_category_for_different_cooperatives(): void
    {
        $cooperativeA = Cooperative::factory()->active()->create();
        $cooperativeB = Cooperative::factory()->active()->create();

        $globalCategory = Category::factory()
            ->global()
            ->create();

        $productA = Product::factory()
            ->forCooperative($cooperativeA)
            ->create();

        $productB = Product::factory()
            ->forCooperative($cooperativeB)
            ->create();

        DB::table('category_product')->insert([
            [
                'category_id' => $globalCategory->getKey(),
                'product_id' => $productA->getKey(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'category_id' => $globalCategory->getKey(),
                'product_id' => $productB->getKey(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->assertDatabaseHas('category_product', [
            'category_id' => $globalCategory->getKey(),
            'product_id' => $productA->getKey(),
        ]);

        $this->assertDatabaseHas('category_product', [
            'category_id' => $globalCategory->getKey(),
            'product_id' => $productB->getKey(),
        ]);
    }

    public function test_database_rejects_product_owner_change_that_invalidates_catalog_relation(): void
    {
        $cooperativeA = Cooperative::factory()->active()->create();
        $cooperativeB = Cooperative::factory()->active()->create();

        $catalogA = Catalog::factory()->create([
            'cooperative_id' => $cooperativeA->getKey(),
            'is_active' => true,
        ]);

        $productA = Product::factory()
            ->forCooperative($cooperativeA)
            ->create();

        $catalogA->products()->attach($productA->getKey());

        try {
            DB::table('products')
                ->where('id', $productA->getKey())
                ->update([
                    'cooperative_id' => $cooperativeB->getKey(),
                ]);

            $this->fail(
                'MySQL aurait dû refuser le changement de propriétaire.'
            );
        } catch (QueryException $exception) {
            $this->assertStringContainsString(
                'cooperative',
                strtolower($exception->getMessage())
            );
        }

        $this->assertDatabaseHas('products', [
            'id' => $productA->getKey(),
            'cooperative_id' => $cooperativeA->getKey(),
        ]);

        $this->assertDatabaseHas('catalog_product', [
            'catalog_id' => $catalogA->getKey(),
            'product_id' => $productA->getKey(),
        ]);
    }

}
