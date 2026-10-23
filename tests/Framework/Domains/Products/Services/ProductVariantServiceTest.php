<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Products\Services;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Products\Models\Product;
use App\Domains\Products\Models\ProductVariant;
use App\Domains\Products\Services\ProductVariantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ProductVariantServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProductVariantService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ProductVariantService::class);
    }

    private function createProduct(): Product
    {
        $cooperative = Cooperative::factory()->create();

        return Product::factory()->create([
            'cooperative_id' => $cooperative->getKey(),
            'is_active' => true,
        ]);
    }

    public function test_it_creates_a_variant_for_its_product(): void
    {
        $product = $this->createProduct();

        $variant = $this->service->create($product, [
            'name' => 'Pot en verre 250 g',
            'sku' => 'MIEL-250',
            'attributes' => [
                'weight' => '250 g',
                'packaging' => 'glass',
            ],
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->getKey(),
            'product_id' => $product->getKey(),
            'cooperative_id' => $product->cooperative_id,
            'name' => 'Pot en verre 250 g',
            'normalized_label' => 'pot en verre 250 g',
            'sku' => 'MIEL-250',
            'is_active' => true,
        ]);

        $this->assertSame(
            $product->getKey(),
            $variant->product->getKey(),
        );
    }

    public function test_it_normalizes_variant_labels(): void
    {
        $product = $this->createProduct();

        $variant = $this->service->create($product, [
            'name' => '  Pot   en VERRE 250 g  ',
            'is_active' => true,
        ]);

        $this->assertSame(
            'pot en verre 250 g',
            $variant->normalized_label,
        );
    }

    public function test_it_rejects_duplicate_active_labels_for_same_product(): void
    {
        $product = $this->createProduct();

        $this->service->create($product, [
            'name' => 'Pot en verre 250 g',
            'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);

        $this->service->create($product, [
            'name' => '  POT EN VERRE 250 G  ',
            'is_active' => true,
        ]);
    }

    public function test_different_products_can_use_the_same_variant_label(): void
    {
        $firstProduct = $this->createProduct();
        $secondProduct = $this->createProduct();

        $first = $this->service->create($firstProduct, [
            'name' => 'Pot 250 g',
            'is_active' => true,
        ]);

        $second = $this->service->create($secondProduct, [
            'name' => 'Pot 250 g',
            'is_active' => true,
        ]);

        $this->assertNotSame(
            $first->getKey(),
            $second->getKey(),
        );

        $this->assertSame(
            $first->normalized_label,
            $second->normalized_label,
        );
    }

    public function test_it_rejects_creation_for_an_inactive_product(): void
    {
        $product = $this->createProduct();

        $product->update(['is_active' => false]);

        $this->expectException(ValidationException::class);

        $this->service->create($product, [
            'name' => 'Pot 250 g',
            'is_active' => true,
        ]);
    }

    public function test_it_soft_deletes_a_variant_without_physical_deletion(): void
    {
        $product = $this->createProduct();

        $variant = $this->service->create($product, [
            'name' => 'Pot 250 g',
            'is_active' => true,
        ]);

        $variant->delete();

        $this->assertSoftDeleted('product_variants', [
            'id' => $variant->getKey(),
        ]);

        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->getKey(),
        ]);
    }
}
