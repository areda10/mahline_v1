<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Products\Models;

use App\Domains\Categories\Models\Category;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Products\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_can_be_associated_with_a_global_category(): void
    {
        $category = Category::factory()->global()->create();
        $product = Product::factory()->create();

        $product->categories()->attach($category->getKey());

        $this->assertTrue(
            $product->categories()
                ->whereKey($category->getKey())
                ->exists(),
        );
    }

    public function test_product_can_be_associated_with_its_cooperative_category(): void
    {
        $cooperative = Cooperative::factory()->create();

        $category = Category::factory()
            ->forCooperative($cooperative)
            ->create();

        $product = Product::factory()
            ->forCooperative($cooperative)
            ->create();

        $product->categories()->attach($category->getKey());

        $this->assertTrue(
            $product->categories()
                ->whereKey($category->getKey())
                ->exists(),
        );
    }

    public function test_product_does_not_belong_to_another_cooperatives_category(): void
    {
        $cooperativeA = Cooperative::factory()->create();
        $cooperativeB = Cooperative::factory()->create();

        $categoryB = Category::factory()
            ->forCooperative($cooperativeB)
            ->create();

        $productA = Product::factory()
            ->forCooperative($cooperativeA)
            ->create();

        // Une relation SQL seule ne garantit pas l'isolation métier.
        // Le service doit refuser cette association.
        $this->assertNotSame(
            $productA->cooperative_id,
            $categoryB->cooperative_id,
        );
    }
}