<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Products\Factories;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Products\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProductFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_product_for_a_cooperative(): void
    {
        $cooperative = Cooperative::factory()->create();

        $product = Product::factory()
            ->forCooperative($cooperative)
            ->create();

        $this->assertSame(
            $cooperative->getKey(),
            $product->cooperative_id,
        );

        $this->assertDatabaseHas('products', [
            'id' => $product->getKey(),
            'cooperative_id' => $cooperative->getKey(),
        ]);
    }

    public function test_it_can_create_an_inactive_product(): void
    {
        $product = Product::factory()->inactive()->create();

        $this->assertFalse((bool) $product->is_active);
    }
}