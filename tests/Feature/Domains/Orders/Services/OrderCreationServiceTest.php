<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Orders\Services;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Inventory\Models\InventoryStock;
use App\Domains\Orders\Services\OrderCreationService;
use App\Domains\Products\Models\Product;
use App\Domains\Products\Models\ProductUnit;
use App\Domains\Products\Models\ProductVariant;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ProductUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class OrderCreationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class, ProductUnitSeeder::class]);
    }

    public function test_checkout_creates_one_order_with_cooperative_segments_and_price_snapshots(): void
    {
        $customer = $this->customer();
        $cooperativeA = Cooperative::factory()->create();
        $cooperativeB = Cooperative::factory()->create();

        $productA = Product::factory()->forCooperative($cooperativeA)->create([
            'price' => '20.00',
            'currency' => 'MAD',
        ]);
        $productB = Product::factory()->forCooperative($cooperativeB)->create([
            'price' => '10.00',
            'currency' => 'MAD',
        ]);
        $productA->update(['unit_id' => ProductUnit::query()->where('code', 'u')->value('id')]);
        $productB->update(['unit_id' => ProductUnit::query()->where('code', 'kg')->value('id')]);
        $this->stock($cooperativeA, 'product', (string) $productA->getKey(), 'u', '10.000');
        $this->stock($cooperativeB, 'product', (string) $productB->getKey(), 'kg', '10.000');

        $order = app(OrderCreationService::class)->createFromCheckout($customer, [
            'idempotency_key' => 'test-order-creation-01',
            'delivery_address_snapshot' => [
                'street' => 'Avenue Hassan II',
                'city' => 'Casablanca',
                'country' => 'Maroc',
            ],
            'items' => [
                [
                    'product_id' => (string) $productA->getKey(),
                    'quantity' => '2',
                    'unit' => 'kg', // Valeur falsifiée : Orders doit reprendre l'unité Products.
                    'unit_price' => '0.01',
                ],
                [
                    'product_id' => (string) $productB->getKey(),
                    'quantity' => '1.000',
                ],
            ],
        ]);

        $this->assertCount(2, $order->cooperativeOrders);
        $this->assertDatabaseCount('inventory_reservations', 0);
        $this->assertDatabaseHas('inventory_stocks', [
            'cooperative_id' => $cooperativeA->getKey(),
            'stockable_id' => $productA->getKey(),
            'quantity_reserved' => '0.000',
        ]);
        $this->assertSame('50.00', $order->items_subtotal);
        $this->assertSame('50.00', $order->grand_total);
        $this->assertSame('MAD', $order->currency);
        $this->assertSame(
            'Unité / pièce (u)',
            $order->cooperativeOrders->firstWhere('cooperative_id', $cooperativeA->getKey())
                ->items->first()->product_snapshot['unit_label'],
        );
        $this->assertDatabaseHas('order_items', [
            'product_id' => $productA->getKey(),
            'unit_price' => '20.00',
            'quantity' => '2.000',
            'line_total' => '40.00',
            'unit' => 'u',
        ]);
        $this->assertDatabaseHas('order_items', [
            'product_id' => $productB->getKey(),
            'unit_price' => '10.00',
            'line_total' => '10.00',
        ]);
        $this->assertDatabaseHas('order_status_histories', [
            'subject_type' => 'order',
            'subject_id' => $order->getKey(),
            'to_status' => 'awaiting_cooperatives',
        ]);
    }

    public function test_checkout_uses_variant_override_price_and_rejects_unpriced_products(): void
    {
        $customer = $this->customer();
        $cooperative = Cooperative::factory()->create();
        $product = Product::factory()->forCooperative($cooperative)->create([
            'price' => '80.00',
            'currency' => 'MAD',
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->getKey(),
            'price' => '92.50',
            'unit_id' => ProductUnit::query()->where('code', 'ml')->value('id'),
        ]);
        $this->stock($cooperative, 'variant', (string) $variant->getKey(), 'ml', '10.000');

        $order = app(OrderCreationService::class)->createFromCheckout($customer, [
            'idempotency_key' => 'test-order-creation-02',
            'delivery_address_snapshot' => ['city' => 'Rabat', 'country' => 'Maroc'],
            'items' => [[
                'product_id' => (string) $product->getKey(),
                'product_variant_id' => (string) $variant->getKey(),
                'quantity' => '1',
            ]],
        ]);

        $this->assertSame('92.50', $order->cooperativeOrders->first()->items->first()->unit_price);
        $this->assertSame('ml', $order->cooperativeOrders->first()->items->first()->unit);
        $this->assertSame('Millilitre (ml)', $order->cooperativeOrders->first()->items->first()->product_snapshot['unit_label']);

        $unpriced = Product::factory()->forCooperative($cooperative)->create([
            'price' => null,
            'unit_id' => ProductUnit::query()->where('code', 'kg')->value('id'),
        ]);

        $this->expectException(ValidationException::class);

        app(OrderCreationService::class)->createFromCheckout($customer, [
            'idempotency_key' => 'test-order-creation-03',
            'delivery_address_snapshot' => ['city' => 'Rabat', 'country' => 'Maroc'],
            'items' => [[
                'product_id' => (string) $unpriced->getKey(),
                'quantity' => '1',
            ]],
        ]);
    }

    public function test_checkout_requires_a_variant_when_the_product_has_variants(): void
    {
        $customer = $this->customer();
        $cooperative = Cooperative::factory()->create();
        $product = Product::factory()->forCooperative($cooperative)->create([
            'price' => '20.00',
            'currency' => 'MAD',
            'unit_id' => null,
        ]);
        ProductVariant::factory()->create([
            'product_id' => $product->getKey(),
            'price' => '22.00',
            'unit_id' => ProductUnit::query()->where('code', 'u')->value('id'),
        ]);

        $this->expectException(ValidationException::class);

        app(OrderCreationService::class)->createFromCheckout($customer, [
            'idempotency_key' => 'test-order-creation-04',
            'delivery_address_snapshot' => ['city' => 'Casablanca', 'country' => 'Maroc'],
            'items' => [[
                'product_id' => (string) $product->getKey(),
                'quantity' => '1',
            ]],
        ]);
    }

    public function test_checkout_rejects_parent_unit_when_a_variant_is_selected(): void
    {
        $customer = $this->customer();
        $cooperative = Cooperative::factory()->create();
        $product = Product::factory()->forCooperative($cooperative)->create([
            'price' => '20.00',
            'currency' => 'MAD',
            'unit_id' => ProductUnit::query()->where('code', 'kg')->value('id'),
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->getKey(),
            'price' => '22.00',
            'unit_id' => ProductUnit::query()->where('code', 'u')->value('id'),
        ]);

        $this->expectException(ValidationException::class);

        app(OrderCreationService::class)->createFromCheckout($customer, [
            'idempotency_key' => 'test-order-creation-05',
            'delivery_address_snapshot' => ['city' => 'Casablanca', 'country' => 'Maroc'],
            'items' => [[
                'product_id' => (string) $product->getKey(),
                'product_variant_id' => (string) $variant->getKey(),
                'quantity' => '1',
            ]],
        ]);
    }

    public function test_checkout_rejects_products_without_a_registered_stock_unit(): void
    {
        $customer = $this->customer();
        $cooperative = Cooperative::factory()->create();
        $product = Product::factory()->forCooperative($cooperative)->create([
            'price' => '15.00',
            'currency' => 'MAD',
            'unit_id' => null,
        ]);

        $this->expectException(ValidationException::class);

        app(OrderCreationService::class)->createFromCheckout($customer, [
            'idempotency_key' => 'test-order-creation-06',
            'delivery_address_snapshot' => ['city' => 'Casablanca', 'country' => 'Maroc'],
            'items' => [[
                'product_id' => (string) $product->getKey(),
                'quantity' => '1',
                'unit' => 'u',
            ]],
        ]);
    }

    private function stock(Cooperative $cooperative, string $stockableType, string $stockableId, string $unit, string $quantity): void
    {
        InventoryStock::query()->create([
            'cooperative_id' => (string) $cooperative->getKey(),
            'stockable_type' => $stockableType,
            'stockable_id' => $stockableId,
            'unit_code' => $unit,
            'quantity_on_hand' => $quantity,
        ]);
    }

    private function customer(): User
    {
        $role = Role::query()->firstOrCreate(
            ['slug' => 'customer'],
            ['name' => 'Customer', 'is_active' => true],
        );

        $customer = User::factory()->create([
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
        ]);
        $customer->roles()->attach($role->getKey());

        return $customer;
    }
}
