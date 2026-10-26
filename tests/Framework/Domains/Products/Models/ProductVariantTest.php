<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Products\Models;

use App\Domains\Products\Models\Product;
use App\Domains\Products\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProductVariantTest extends TestCase
{
    use RefreshDatabase;

    public function test_variant_belongs_to_a_product(): void
    {
        $product = Product::factory()->create();

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->getKey(),
        ]);

        $this->assertTrue(
            $variant->product->is($product),
        );
    }

    public function test_variant_attributes_are_cast_to_an_array(): void
    {
        $product = Product::factory()->create();

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->getKey(),
            'attributes' => [
                'weight' => '250 g',
                'packaging' => 'glass',
            ],
        ]);

        $variant->refresh();

        $this->assertIsArray($variant->attributes);
        $this->assertSame('250 g', $variant->attributes['weight']);
        $this->assertSame('glass', $variant->attributes['packaging']);
    }

    public function test_variant_can_have_a_null_sku(): void
    {
        $product = Product::factory()->create();

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->getKey(),
            'sku' => null,
        ]);

        $this->assertNull($variant->fresh()->sku);
    }

    public function test_variant_can_be_soft_deleted(): void
    {
        $product = Product::factory()->create();

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->getKey(),
        ]);

        $variantId = $variant->getKey();

        $variant->delete();

        $this->assertSoftDeleted('product_variants', [
            'id' => $variantId,
        ]);

        $this->assertNotNull(
            ProductVariant::withTrashed()->find($variantId),
        );
    }

    public function test_product_relationship_excludes_soft_deleted_variants_by_default(): void
    {
        $product = Product::factory()->create();

        $activeVariant = ProductVariant::factory()->create([
            'product_id' => $product->getKey(),
        ]);

        $deletedVariant = ProductVariant::factory()->create([
            'product_id' => $product->getKey(),
        ]);

        $deletedVariant->delete();

        $this->assertTrue(
            $product->variants()
                ->whereKey($activeVariant->getKey())
                ->exists(),
        );

        $this->assertFalse(
            $product->variants()
                ->whereKey($deletedVariant->getKey())
                ->exists(),
        );
    }
}