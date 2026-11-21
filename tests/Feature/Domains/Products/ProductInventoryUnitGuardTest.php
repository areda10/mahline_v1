<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Products;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Inventory\Enums\InventoryStockableType;
use App\Domains\Inventory\Models\InventoryStock;
use App\Domains\Products\Models\Product;
use App\Domains\Products\Models\ProductUnit;
use App\Domains\Products\Models\ProductVariant;
use App\Domains\Products\Services\ProductService;
use App\Domains\Products\Services\ProductVariantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ProductInventoryUnitGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_unit_cannot_change_after_inventory_stock_is_initialized(): void
    {
        $cooperative = Cooperative::factory()->create();
        $unitKg = $this->unit('kg');
        $unitG = $this->unit('g');
        $product = Product::factory()->forCooperative($cooperative)->create([
            'unit_id' => $unitKg->getKey(),
        ]);

        InventoryStock::query()->create([
            'cooperative_id' => (string) $cooperative->getKey(),
            'stockable_type' => InventoryStockableType::PRODUCT->value,
            'stockable_id' => (string) $product->getKey(),
            'unit_code' => 'kg',
            'quantity_on_hand' => '0.000',
        ]);

        try {
            app(ProductService::class)->update(
                $product,
                (string) $cooperative->getKey(),
                ['unit_id' => (string) $unitG->getKey()],
            );
            $this->fail('L’unité du produit ne doit plus changer après initialisation du stock.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('unit_id', $exception->errors());
        }
    }

    public function test_variant_unit_cannot_change_after_inventory_stock_is_initialized(): void
    {
        $cooperative = Cooperative::factory()->create();
        $unitKg = $this->unit('kg');
        $unitG = $this->unit('g');
        $product = Product::factory()->forCooperative($cooperative)->create([
            'unit_id' => null,
        ]);
        $variant = ProductVariant::factory()->for($product)->create([
            'unit_id' => $unitKg->getKey(),
        ]);

        InventoryStock::query()->create([
            'cooperative_id' => (string) $cooperative->getKey(),
            'stockable_type' => InventoryStockableType::VARIANT->value,
            'stockable_id' => (string) $variant->getKey(),
            'unit_code' => 'kg',
            'quantity_on_hand' => '0.000',
        ]);

        try {
            app(ProductVariantService::class)->update($variant, [
                'unit_id' => (string) $unitG->getKey(),
            ]);
            $this->fail('L’unité de la variante ne doit plus changer après initialisation du stock.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('unit_id', $exception->errors());
        }
    }

    private function unit(string $code): ProductUnit
    {
        return ProductUnit::query()->firstOrCreate(
            ['code' => $code],
            [
                'label' => $code,
                'dimension' => in_array($code, ['kg', 'g'], true) ? 'mass' : 'count',
                'sort_order' => 1,
                'is_active' => true,
            ],
        );
    }
}
