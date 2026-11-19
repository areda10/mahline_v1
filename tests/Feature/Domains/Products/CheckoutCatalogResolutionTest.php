<?php

declare(strict_types=1);

namespace Tests\\Feature\\Domains\\Products;

use App\\Domains\\Products\\Contracts\\CheckoutCatalog;
use App\\Domains\\Products\\Models\\Product;
use App\\Domains\\Products\\Models\\ProductVariant;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Tests\\TestCase;

final class CheckoutCatalogResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_with_variants_is_reported_as_having_variants_when_a_variant_is_requested(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create();

        $data = app(CheckoutCatalog::class)->resolveForCheckout(
            (string) $product->getKey(),
            (string) $variant->getKey(),
        );

        $this->assertNotNull($data);
        $this->assertTrue($data->hasVariants);
        $this->assertTrue($data->variantRequested);
        $this->assertTrue($data->variantFound);
        $this->assertSame((string) $variant->getKey(), $data->variantId);
    }

    public function test_product_with_variants_is_reported_as_having_variants_when_no_variant_is_requested(): void
    {
        $product = Product::factory()->create();
        ProductVariant::factory()->for($product)->create();

        $data = app(CheckoutCatalog::class)->resolveForCheckout(
            (string) $product->getKey(),
            null,
        );

        $this->assertNotNull($data);
        $this->assertTrue($data->hasVariants);
        $this->assertFalse($data->variantRequested);
    }

    public function test_missing_variant_is_not_reported_as_found(): void
    {
        $product = Product::factory()->create();
        ProductVariant::factory()->for($product)->create();

        $data = app(CheckoutCatalog::class)->resolveForCheckout(
            (string) $product->getKey(),
            '01J00000000000000000000000',
        );

        $this->assertNotNull($data);
        $this->assertTrue($data->hasVariants);
        $this->assertTrue($data->variantRequested);
        $this->assertFalse($data->variantFound);
        $this->assertNull($data->variantId);
    }

    public function test_product_without_variants_is_reported_correctly(): void
    {
        $product = Product::factory()->create();

        $data = app(CheckoutCatalog::class)->resolveForCheckout(
            (string) $product->getKey(),
            null,
        );

        $this->assertNotNull($data);
        $this->assertFalse($data->hasVariants);
        $this->assertFalse($data->variantRequested);
    }
}
