<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Products;

use App\Domains\Products\Models\Product;
use App\Domains\Products\Models\ProductVariant;
use App\Domains\Products\Repositories\ProductRepository;
use App\Domains\Products\Services\ProductService;
use App\Domains\Products\Services\ProductVariantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ProductLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_soft_deleting_a_product_preserves_variant_rows_and_states(): void
    {
        $product = Product::factory()->create(['is_active' => true]);
        $activeVariant = ProductVariant::factory()->for($product)->create([
            'is_active' => true,
        ]);
        $inactiveVariant = ProductVariant::factory()->for($product)->inactive()->create();

        $service = app(ProductService::class);

        $this->assertTrue($service->delete($product));

        $this->assertSoftDeleted('products', ['id' => $product->getKey()]);
        $this->assertDatabaseHas('product_variants', [
            'id' => $activeVariant->getKey(),
            'is_active' => true,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('product_variants', [
            'id' => $inactiveVariant->getKey(),
            'is_active' => false,
            'deleted_at' => null,
        ]);
    }

    public function test_restoring_a_product_does_not_change_variant_states(): void
    {
        $product = Product::factory()->create(['is_active' => true]);
        $activeVariant = ProductVariant::factory()->for($product)->create([
            'is_active' => true,
        ]);
        $inactiveVariant = ProductVariant::factory()->for($product)->inactive()->create();

        $service = new ProductService(new ProductRepository());
        $service->delete($product);

        $trashedProduct = Product::withTrashed()->findOrFail($product->getKey());

        $this->assertTrue($service->restore($trashedProduct));

        $this->assertDatabaseHas('products', [
            'id' => $product->getKey(),
            'deleted_at' => null,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('product_variants', [
            'id' => $activeVariant->getKey(),
            'is_active' => true,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('product_variants', [
            'id' => $inactiveVariant->getKey(),
            'is_active' => false,
            'deleted_at' => null,
        ]);
    }

    public function test_variant_cannot_be_updated_when_parent_product_is_inactive(): void
    {
        $product = Product::factory()->inactive()->create();
        $variant = ProductVariant::factory()->for($product)->create();

        try {
            (new ProductVariantService())->update($variant, ['sku' => 'UPDATED-SKU']);
            $this->fail('Une variante d’un produit inactif ne doit pas être modifiable.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('product', $exception->errors());
        }
    }

    public function test_variant_cannot_be_updated_when_parent_product_is_soft_deleted(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create();
        $product->delete();

        try {
            (new ProductVariantService())->update($variant, ['sku' => 'UPDATED-SKU']);
            $this->fail('Une variante d’un produit supprimé ne doit pas être modifiable.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('product', $exception->errors());
        }
    }
}
