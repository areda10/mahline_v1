<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Products\Repositories;

use App\Domains\Products\Models\Product;
use App\Domains\Products\Models\ProductVariant;
use App\Domains\Products\Repositories\ProductVariantRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProductVariantRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private ProductVariantRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app(ProductVariantRepository::class);
    }

    public function test_it_paginates_variants_for_one_product(): void
    {
        $productA = Product::factory()->create();
        $productB = Product::factory()->create();

        ProductVariant::factory()->count(3)->create([
            'product_id' => $productA->getKey(),
        ]);

        ProductVariant::factory()->count(2)->create([
            'product_id' => $productB->getKey(),
        ]);

        $page = $this->repository->paginateForProduct(
            (string) $productA->getKey(),
        );

        $this->assertSame(3, $page->total());

        foreach ($page->items() as $variant) {
            $this->assertSame(
                $productA->getKey(),
                $variant->product_id,
            );
        }
    }

    public function test_it_finds_a_variant_belonging_to_the_product(): void
    {
        $product = Product::factory()->create();

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->getKey(),
        ]);

        $result = $this->repository->findForProductOrFail(
            (string) $variant->getKey(),
            (string) $product->getKey(),
        );

        $this->assertSame(
            $variant->getKey(),
            $result->getKey(),
        );
    }

    public function test_it_rejects_a_variant_from_another_product(): void
    {
        $productA = Product::factory()->create();
        $productB = Product::factory()->create();

        $variant = ProductVariant::factory()->create([
            'product_id' => $productA->getKey(),
        ]);

        $this->expectException(ModelNotFoundException::class);

        $this->repository->findForProductOrFail(
            (string) $variant->getKey(),
            (string) $productB->getKey(),
        );
    }

    public function test_it_does_not_return_soft_deleted_variants(): void
    {
        $product = Product::factory()->create();

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->getKey(),
        ]);

        $variantId = $variant->getKey();

        $variant->delete();

        $this->expectException(ModelNotFoundException::class);

        $this->repository->findForProductOrFail(
            (string) $variantId,
            (string) $product->getKey(),
        );
    }

    public function test_delete_soft_deletes_the_variant(): void
    {
        $product = Product::factory()->create();

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->getKey(),
        ]);

        $variantId = $variant->getKey();

        $this->repository->delete($variant);

        $this->assertSoftDeleted('product_variants', [
            'id' => $variantId,
        ]);
    }
}
