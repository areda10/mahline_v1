<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Products\Services;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Products\Models\Product;
use App\Domains\Products\Models\ProductUnit;
use Database\Seeders\ProductUnitSeeder;
use App\Domains\Products\Services\ProductVariantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ProductVariantServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProductVariantService $service;

    private string $unitId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ProductUnitSeeder::class);
        $this->unitId = (string) ProductUnit::query()->where('code', 'kg')->value('id');
        $this->service = app(ProductVariantService::class);
    }

    private function createProduct(): Product
    {
        $cooperative = Cooperative::factory()->create();

        return Product::factory()->create([
            'cooperative_id' => $cooperative->getKey(),
            'product_type' => 'variant',
            'unit_id' => null,
            'is_active' => true,
        ]);
    }

    public function test_it_creates_a_variant_for_its_product(): void
    {
        $product = $this->createProduct();

        $variant = $this->service->create($product, [
            'name' => 'Pot en verre 250 g',
            'unit_id' => $this->unitId,
            'price' => '12.50',
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
            'label' => 'Pot en verre 250 g',
            'normalized_label' => 'pot en verre 250 g',
            'sku' => 'MIEL-250',
            'is_active' => true,
        ]);

        $this->assertSame(
            $product->getKey(),
            $variant->product->getKey(),
        );
    }

    public function test_it_requires_and_normalizes_a_variant_price_to_two_decimals(): void
    {
        $product = $this->createProduct();

        $variant = $this->service->create($product, [
            'name' => 'Pot 250 g',
            'unit_id' => $this->unitId,
            'price' => '12.5',
            'is_active' => true,
        ]);

        $this->assertSame('12.50', $variant->fresh()->price);
        $this->assertSame('12.50', $variant->effectivePrice());
    }


    public function test_it_blocks_variant_creation_until_parent_stock_transfer_is_available(): void
    {
        $product = $this->createProduct();
        $parentUnit = ProductUnit::query()->where('code', 'l')->firstOrFail();
        $product->update(['unit_id' => $parentUnit->getKey()]);

        try {
            $this->service->create($product, [
                'name' => 'Bouteille 500 ml',
                'price' => '12.50',
                'unit_id' => $this->unitId,
                'is_active' => true,
            ]);

            $this->fail('La conversion doit attendre le transfert atomique du stock parent.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('unit_id', $exception->errors());
        }

        $this->assertSame($parentUnit->getKey(), $product->fresh()->unit_id);
        $this->assertDatabaseMissing('product_variants', [
            'product_id' => $product->getKey(),
            'normalized_label' => 'bouteille 500 ml',
        ]);
    }

    public function test_it_accepts_piece_unit_for_a_variant(): void
    {
        $product = $this->createProduct();
        $pieceUnit = ProductUnit::query()->where('code', 'u')->firstOrFail();

        $variant = $this->service->create($product, [
            'name' => 'Pièce individuelle',
            'unit_id' => (string) $pieceUnit->getKey(),
            'price' => '3.50',
            'is_active' => true,
        ]);

        $this->assertSame('u', $variant->unit->code);
    }

    public function test_it_requires_a_registered_unit_for_a_new_variant(): void
    {
        $product = $this->createProduct();

        $this->expectException(ValidationException::class);

        $this->service->create($product, [
            'name' => 'Pot sans unité',
            'price' => '12.50',
            'is_active' => true,
        ]);
    }

    public function test_it_rejects_a_missing_variant_price(): void
    {
        $product = $this->createProduct();

        $this->expectException(ValidationException::class);

        $this->service->create($product, [
            'name' => 'Pot sans prix',
            'unit_id' => $this->unitId,
            'is_active' => true,
        ]);
    }

    public function test_it_rejects_a_null_variant_price(): void
    {
        $product = $this->createProduct();

        $this->expectException(ValidationException::class);

        $this->service->create($product, [
            'name' => 'Pot sans prix',
            'unit_id' => $this->unitId,
            'price' => null,
            'is_active' => true,
        ]);
    }

    public function test_it_rejects_a_zero_variant_price_override(): void
    {
        $product = $this->createProduct();

        $this->expectException(ValidationException::class);

        $this->service->create($product, [
            'name' => 'Pot gratuit',
            'unit_id' => $this->unitId,
            'price' => '0.00',
            'is_active' => true,
        ]);
    }

    public function test_it_normalizes_variant_labels(): void
    {
        $product = $this->createProduct();

        $variant = $this->service->create($product, [
            'name' => '  Pot   en VERRE 250 g  ',
            'unit_id' => $this->unitId,
            'price' => '12.50',
            'is_active' => true,
        ]);

        $this->assertSame(
            'pot en verre 250 g',
            $variant->normalized_label,
        );

        $this->assertSame(
            'Pot   en VERRE 250 g',
            $variant->label,
        );
    }

    public function test_it_rejects_duplicate_active_labels_for_same_product(): void
    {
        $product = $this->createProduct();

        $this->service->create($product, [
            'name' => 'Pot en verre 250 g',
            'unit_id' => $this->unitId,
            'price' => '12.50',
            'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);

        $this->service->create($product, [
            'name' => '  POT EN VERRE 250 G  ',
            'unit_id' => $this->unitId,
            'price' => '12.50',
            'is_active' => true,
        ]);
    }

    public function test_different_products_can_use_the_same_variant_label(): void
    {
        $firstProduct = $this->createProduct();
        $secondProduct = $this->createProduct();

        $first = $this->service->create($firstProduct, [
            'name' => 'Pot 250 g',
            'unit_id' => $this->unitId,
            'price' => '12.50',
            'is_active' => true,
        ]);

        $second = $this->service->create($secondProduct, [
            'name' => 'Pot 250 g',
            'unit_id' => $this->unitId,
            'price' => '12.50',
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
            'unit_id' => $this->unitId,
            'price' => '12.50',
            'is_active' => true,
        ]);
    }

    public function test_it_soft_deletes_a_variant_without_physical_deletion(): void
    {
        $product = $this->createProduct();

        $variant = $this->service->create($product, [
            'name' => 'Pot 250 g',
            'unit_id' => $this->unitId,
            'price' => '12.50',
            'is_active' => true,
        ]);

        $variantId = $variant->getKey();

        $variant->delete();

        $this->assertSoftDeleted('product_variants', [
            'id' => $variantId,
        ]);

        $this->assertDatabaseHas('product_variants', [
            'id' => $variantId,
        ]);
    }
}
