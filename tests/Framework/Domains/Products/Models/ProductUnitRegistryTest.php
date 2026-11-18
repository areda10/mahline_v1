<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Products\Models;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Products\Models\Product;
use App\Domains\Products\Models\ProductUnit;
use App\Domains\Products\Models\ProductVariant;
use Database\Seeders\ProductUnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProductUnitRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_only_the_four_approved_v1_units(): void
    {
        $this->seed(ProductUnitSeeder::class);

        $this->assertSame(
            ['g', 'kg', 'l', 'ml'],
            ProductUnit::query()->orderBy('code')->pluck('code')->all(),
        );
    }

    public function test_products_and_variants_can_reference_a_registered_unit(): void
    {
        $this->seed(ProductUnitSeeder::class);

        $cooperative = Cooperative::factory()->create();
        $kilogram = ProductUnit::query()->where('code', 'kg')->firstOrFail();
        $gram = ProductUnit::query()->where('code', 'g')->firstOrFail();

        $product = Product::factory()->create([
            'cooperative_id' => $cooperative->getKey(),
            'unit_id' => $kilogram->getKey(),
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->getKey(),
            'unit_id' => $gram->getKey(),
        ]);

        $this->assertSame($kilogram->getKey(), $product->fresh()->unit->getKey());
        $this->assertSame($gram->getKey(), $variant->fresh()->unit->getKey());
        $this->assertTrue($kilogram->fresh()->products->contains('id', $product->getKey()));
        $this->assertTrue($gram->fresh()->variants->contains('id', $variant->getKey()));
    }
}
