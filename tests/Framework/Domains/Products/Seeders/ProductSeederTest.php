<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Products\Seeders;

use App\Domains\Categories\Models\Category;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Products\Models\Product;
use Database\Seeders\CategorySeeder;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProductSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_products_for_existing_cooperatives(): void
    {
        $cooperative = Cooperative::factory()->create();

        $this->seed(CategorySeeder::class);
        $this->seed(ProductSeeder::class);

        $products = Product::query()
            ->where('cooperative_id', $cooperative->getKey())
            ->get();

        $this->assertCount(3, $products);

        $category = Category::query()
            ->where('slug', 'miels-amlou-confitures')
            ->whereNull('cooperative_id')
            ->firstOrFail();

        foreach ($products as $product) {
            $this->assertTrue(
                $product->categories()
                    ->whereKey($category->getKey())
                    ->exists(),
            );
        }
    }

    public function test_running_the_product_seeder_twice_does_not_duplicate_products(): void
    {
        Cooperative::factory()->create();

        $this->seed(CategorySeeder::class);
        $this->seed(ProductSeeder::class);
        $this->seed(ProductSeeder::class);

        $this->assertSame(
            3,
            Product::query()->count(),
        );
    }
}