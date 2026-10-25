<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Products\Factories;

use App\Domains\Products\Models\Product;
use App\Domains\Products\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProductVariantFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_variant_for_a_product(): void
    {
        $product = Product::factory()->create();

        $variant = ProductVariant::factory()
            ->for($product, 'product')
            ->create();

        $this->assertSame(
            $product->getKey(),
            $variant->product_id,
        );

        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->getKey(),
            'product_id' => $product->getKey(),
        ]);
    }

    public function test_variant_attributes_are_cast_to_an_array(): void
    {
        $variant = ProductVariant::factory()->create();

        $this->assertIsArray($variant->attributes);
    }
}