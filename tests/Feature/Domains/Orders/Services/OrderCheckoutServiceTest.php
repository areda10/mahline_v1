<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Orders\Services;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Inventory\Models\InventoryStock;
use App\Domains\Orders\Contracts\OrderCheckoutCreator;
use App\Domains\Orders\DTOs\OrderCheckoutData;
use App\Domains\Products\Models\Product;
use App\Domains\Products\Models\ProductUnit;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ProductUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OrderCheckoutServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class, ProductUnitSeeder::class]);
    }

    public function test_public_checkout_contract_creates_an_idempotent_order_without_exposing_identity_model(): void
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

        $cooperative = Cooperative::factory()->active()->create();
        $unitId = ProductUnit::query()->where('code', 'u')->value('id');
        $product = Product::factory()->forCooperative($cooperative)->create([
            'price' => '25.00',
            'currency' => 'MAD',
            'unit_id' => $unitId,
        ]);

        InventoryStock::query()->create([
            'cooperative_id' => (string) $cooperative->getKey(),
            'stockable_type' => 'product',
            'stockable_id' => (string) $product->getKey(),
            'unit_code' => 'u',
            'quantity_on_hand' => '5.000',
        ]);

        $checkout = new OrderCheckoutData(
            customerId: (string) $customer->getKey(),
            idempotencyKey: 'sales-checkout-contract-001',
            deliveryAddressSnapshot: [
                'street' => 'Avenue Hassan II',
                'city' => 'Casablanca',
                'country' => 'Maroc',
            ],
            items: [[
                'product_id' => (string) $product->getKey(),
                'quantity' => '2.000',
            ]],
            deliveryQuotes: [[
                'quote_reference' => 'TEST-QUOTE-ORDER-CHECKOUT-001',
                'cooperative_id' => (string) $cooperative->getKey(),
                'amount' => '40.00',
                'currency' => 'MAD',
                'tariff_code' => 'TEST_INITIAL_FLAT',
                'issued_at' => now()->toIso8601String(),
                'expires_at' => now()->addMinutes(30)->toIso8601String(),
            ]],
        );

        $creator = app(OrderCheckoutCreator::class);
        $first = $creator->create($checkout);
        $retry = $creator->create($checkout);

        $this->assertSame($first->getKey(), $retry->getKey());
        $this->assertSame('50.00', $first->items_subtotal);
        $this->assertSame('40.00', $first->delivery_total);
        $this->assertSame('90.00', $first->grand_total);
        $this->assertSame('40.00', $first->cooperativeOrders->first()->delivery_fee);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('cooperative_orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseCount('inventory_reservations', 0);
    }
}
