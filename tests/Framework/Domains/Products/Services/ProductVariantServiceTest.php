<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Products\Services;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Products\Models\Product;
use App\Domains\Products\Models\ProductUnit;
use App\Domains\Products\Models\ProductVariant;
use App\Domains\Products\Services\ProductVariantService;
use Database\Seeders\ProductUnitSeeder;
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
            'price' => null,
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

    public function test_inactive_variant_can_reuse_an_active_variant_label(): void
    {
        $product = $this->createProduct();

        $this->service->create($product, [
            'name' => 'Pot 250 g',
            'unit_id' => $this->unitId,
            'price' => '12.50',
            'is_active' => true,
        ]);

        $inactive = $this->service->create($product, [
            'name' => 'POT 250 G',
            'unit_id' => $this->unitId,
            'price' => '13.50',
            'is_active' => false,
        ]);

        $this->assertFalse($inactive->is_active);
        $this->assertSame('pot 250 g', $inactive->normalized_label);
        $this->assertDatabaseCount('product_variants', 2);
    }

    public function test_it_updates_variant_commercial_fields_without_changing_the_parent_type(): void
    {
        $product = $this->createProduct();
        $variant = $this->service->create($product, [
            'name' => 'Pot 250 g',
            'unit_id' => $this->unitId,
            'price' => '12.50',
            'sku' => 'POT-250',
            'is_active' => true,
        ]);

        $updated = $this->service->update($variant, [
            'name' => 'Pot 500 g',
            'unit_id' => $this->unitId,
            'price' => '19.90',
            'sku' => 'POT-500',
            'is_active' => true,
        ]);

        $this->assertSame('Pot 500 g', $updated->label);
        $this->assertSame('pot 500 g', $updated->normalized_label);
        $this->assertSame('19.90', $updated->price);
        $this->assertSame('POT-500', $updated->sku);
        $this->assertSame('variant', $product->fresh()->product_type);
    }

    public function test_it_can_deactivate_and_reactivate_a_variant(): void
    {
        $product = $this->createProduct();
        $variant = $this->service->create($product, [
            'name' => 'Pot 250 g',
            'unit_id' => $this->unitId,
            'price' => '12.50',
            'is_active' => true,
        ]);

        $this->service->deactivate($variant);
        $this->assertFalse($variant->fresh()->is_active);

        $this->service->activate($variant->fresh());
        $this->assertTrue($variant->fresh()->is_active);
    }

    public function test_it_soft_deletes_and_restores_a_variant(): void
    {
        $product = $this->createProduct();
        $variant = $this->service->create($product, [
            'name' => 'Pot 250 g',
            'unit_id' => $this->unitId,
            'price' => '12.50',
            'is_active' => true,
        ]);
        $variantId = (string) $variant->getKey();

        $this->assertTrue($this->service->delete($variant));
        $this->assertSoftDeleted('product_variants', ['id' => $variantId]);
        $this->assertDatabaseHas('product_variants', ['id' => $variantId]);

        $trashed = ProductVariant::withTrashed()->findOrFail($variantId);
        $this->assertTrue($this->service->restore($trashed));
        $this->assertDatabaseHas('product_variants', [
            'id' => $variantId,
            'deleted_at' => null,
        ]);
    }

    public function test_it_preserves_a_disabled_unit_when_editing_variant_details(): void
    {
        $product = $this->createProduct();
        $variant = $this->service->create($product, [
            'name' => 'Variante initiale',
            'unit_id' => $this->unitId,
            'price' => '10.00',
        ]);
        ProductUnit::query()->whereKey($this->unitId)->update(['is_active' => false]);

        $updated = $this->service->update($variant, ['name' => 'Variante renommée']);

        $this->assertSame('Variante renommée', $updated->label);
        $this->assertSame($this->unitId, (string) $updated->unit_id);
    }

    public function test_variant_lifecycle_persists_audit_actor_columns(): void
    {
        $product = $this->createProduct();
        $actor = User::factory()->create();
        $variant = $this->service->create($product, [
            'name' => 'Variante auditée',
            'unit_id' => $this->unitId,
            'price' => '10.00',
        ], $actor);

        $this->assertSame((string) $actor->getKey(), (string) $variant->created_by);

        $this->service->update($variant, ['name' => 'Variante auditée modifiée'], $actor);
        $this->assertSame((string) $actor->getKey(), (string) $variant->fresh()->updated_by);

        $this->service->delete($variant->fresh(), $actor);
        $this->assertSame((string) $actor->getKey(), (string) ProductVariant::withTrashed()->findOrFail($variant->getKey())->deleted_by);

        $this->service->restore(ProductVariant::withTrashed()->findOrFail($variant->getKey()), $actor);
        $this->assertSame((string) $actor->getKey(), (string) $variant->fresh()->updated_by);
    }

    public function test_variant_effective_price_never_falls_back_to_parent_price(): void
    {
        $product = $this->createProduct();
        $product->update(['price' => '99.00']);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->getKey(),
            'unit_id' => $this->unitId,
            'price' => null,
        ]);

        $this->assertNull($variant->effectivePrice());
    }

    public function test_it_rejects_restoring_an_active_variant_when_its_label_is_already_used(): void
    {
        $product = $this->createProduct();
        $deleted = $this->service->create($product, [
            'name' => 'Pot 250 g',
            'unit_id' => $this->unitId,
            'price' => '12.50',
            'is_active' => true,
        ]);
        $this->service->delete($deleted);

        $this->service->create($product, [
            'name' => 'POT 250 G',
            'unit_id' => $this->unitId,
            'price' => '13.50',
            'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);
        $this->service->restore(ProductVariant::withTrashed()->findOrFail($deleted->getKey()));
    }
}
