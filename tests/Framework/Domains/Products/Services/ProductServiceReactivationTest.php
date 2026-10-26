<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Products\Services;

use App\Domains\Categories\Models\Category;
use App\Domains\Products\Models\Product;
use App\Domains\Products\Services\ProductService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ProductServiceReactivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reactivates_an_inactive_product_with_an_active_category(): void
    {
        $product = Product::factory()
            ->inactive()
            ->create();

        $category = Category::factory()
            ->forCooperative((string) $product->cooperative_id)
            ->create();

        $product->categories()->attach($category->getKey());

        $result = app(ProductService::class)->reactivate($product);

        $this->assertTrue($result->is_active);
        $this->assertDatabaseHas('products', [
            'id' => $product->getKey(),
            'is_active' => true,
            'deleted_at' => null,
        ]);
    }

    public function test_it_refuses_reactivation_without_a_category(): void
    {
        $product = Product::factory()
            ->inactive()
            ->create();

        try {
            app(ProductService::class)->reactivate($product);

            $this->fail('Une ValidationException était attendue.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'product',
                $exception->errors(),
            );
        }

        $this->assertDatabaseHas('products', [
            'id' => $product->getKey(),
            'is_active' => false,
        ]);
    }

    public function test_it_refuses_reactivation_when_all_associated_categories_are_inactive(): void
    {
        $product = Product::factory()
            ->inactive()
            ->create();

        $category = Category::factory()
            ->forCooperative((string) $product->cooperative_id)
            ->inactive()
            ->create();

        $product->categories()->attach($category->getKey());

        $this->expectException(ValidationException::class);

        app(ProductService::class)->reactivate($product);
    }

    public function test_it_does_not_reactivate_a_soft_deleted_product(): void
    {
        $product = Product::factory()
            ->inactive()
            ->create();

        $product->delete();

        $this->expectException(ModelNotFoundException::class);

        app(ProductService::class)->reactivate($product);
    }

    public function test_it_does_not_modify_a_product_that_is_already_active(): void
    {
        $product = Product::factory()->create();

        $result = app(ProductService::class)->reactivate($product);

        $this->assertTrue($result->is_active);
        $this->assertDatabaseHas('products', [
            'id' => $product->getKey(),
            'is_active' => true,
        ]);
    }

    public function test_update_cannot_reactivate_an_inactive_product_directly(): void
    {
        $product = Product::factory()
            ->inactive()
            ->create();

        $this->expectException(ValidationException::class);

        app(ProductService::class)->update(
            $product,
            (string) $product->cooperative_id,
            ['is_active' => true],
        );
    }
}
