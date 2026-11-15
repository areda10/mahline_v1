<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Products;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Products\Models\Product;
use App\Domains\Products\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProductCooperativeRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_cooperative_exposes_its_products_and_active_products(): void
    {
        $cooperative = Cooperative::factory()->create();
        $activeProduct = Product::factory()
            ->forCooperative($cooperative)
            ->create(['is_active' => true]);
        $inactiveProduct = Product::factory()
            ->forCooperative($cooperative)
            ->inactive()
            ->create();

        $this->assertTrue(
            $cooperative->products()->whereKey($activeProduct->getKey())->exists()
        );
        $this->assertTrue(
            $cooperative->products()->whereKey($inactiveProduct->getKey())->exists()
        );
        $this->assertTrue(
            $cooperative->activeProducts()->whereKey($activeProduct->getKey())->exists()
        );
        $this->assertFalse(
            $cooperative->activeProducts()->whereKey($inactiveProduct->getKey())->exists()
        );
    }

    public function test_variant_inherits_cooperative_ownership_through_its_product(): void
    {
        $cooperative = Cooperative::factory()->create();
        $product = Product::factory()
            ->forCooperative($cooperative)
            ->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->getKey(),
        ]);

        $this->assertSame(
            (string) $cooperative->getKey(),
            (string) $variant->product->cooperative->getKey(),
        );
    }

    public function test_product_exposes_only_active_variants_through_active_variants_relation(): void
    {
        $product = Product::factory()->create();
        $activeVariant = ProductVariant::factory()->create([
            'product_id' => $product->getKey(),
            'is_active' => true,
        ]);
        $inactiveVariant = ProductVariant::factory()->inactive()->create([
            'product_id' => $product->getKey(),
        ]);

        $this->assertTrue(
            $product->activeVariants()->whereKey($activeVariant->getKey())->exists()
        );
        $this->assertFalse(
            $product->activeVariants()->whereKey($inactiveVariant->getKey())->exists()
        );
    }
}
