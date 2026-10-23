<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Products\Models;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Products\Models\Product;
use App\Domains\Products\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_belongs_to_a_cooperative(): void
    {
        $cooperative = Cooperative::factory()->create();

        $product = Product::factory()->create([
            'cooperative_id' => $cooperative->getKey(),
        ]);

        $this->assertTrue(
            $product->cooperative->is($cooperative),
        );
    }

    public function test_product_has_many_variants(): void
    {
        $product = Product::factory()->create();

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->getKey(),
            'cooperative_id' => $product->cooperative_id,
        ]);

        $this->assertTrue(
            $product->variants()->whereKey($variant->getKey())->exists(),
        );
    }

    public function test_active_scope_returns_only_active_products(): void
    {
        $active = Product::factory()->create([
            'is_active' => true,
        ]);

        Product::factory()->inactive()->create();

        $results = Product::query()->active()->pluck('id');

        $this->assertTrue($results->contains($active->getKey()));
        $this->assertCount(1, $results);
    }

    public function test_for_cooperative_scope_filters_products(): void
    {
        $firstCooperative = Cooperative::factory()->create();
        $secondCooperative = Cooperative::factory()->create();

        $firstProduct = Product::factory()->create([
            'cooperative_id' => $firstCooperative->getKey(),
        ]);

        Product::factory()->create([
            'cooperative_id' => $secondCooperative->getKey(),
        ]);

        $results = Product::query()
            ->forCooperative((string) $firstCooperative->getKey())
            ->get();

        $this->assertCount(1, $results);
        $this->assertSame(
            $firstProduct->getKey(),
            $results->first()->getKey(),
        );
    }

    public function test_product_can_be_soft_deleted(): void
    {
        $product = Product::factory()->create();

        $productId = $product->getKey();

        $product->delete();

        $this->assertSoftDeleted('products', [
            'id' => $productId,
        ]);

        $this->assertNull(
            Product::query()->find($productId),
        );

        $this->assertNotNull(
            Product::withTrashed()->find($productId),
        );
    }
}
