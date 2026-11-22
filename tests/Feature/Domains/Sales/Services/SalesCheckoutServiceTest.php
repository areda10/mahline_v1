<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Sales\Services;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Inventory\Models\InventoryStock;
use App\Domains\Localization\Models\Address;
use App\Domains\Orders\Contracts\OrderCheckoutCreator;
use App\Domains\Products\Models\Product;
use App\Domains\Products\Models\ProductUnit;
use App\Domains\Sales\Contracts\SalesCartManager;
use App\Domains\Sales\Models\SalesCart;
use App\Domains\Sales\Services\SalesCheckoutService;
use Database\Seeders\ProductUnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class SalesCheckoutServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProductUnitSeeder::class);
    }

    public function test_confirmation_consumes_only_ordered_lines_and_replay_returns_the_same_order(): void
    {
        $customer = User::factory()->create();
        $address = Address::factory()->forUser($customer)->create([
            'street' => 'Avenue Hassan II',
            'city' => 'Casablanca',
        ]);
        [$availableCooperative, $availableProduct] = $this->productWithStock('4.000');
        [$unavailableCooperative, $unavailableProduct] = $this->productWithStock('0.000');

        $cart = app(SalesCartManager::class)->getOrCreateCustomerCart((string) $customer->getKey());
        $manager = app(SalesCartManager::class);
        $manager->addItem((string) $cart->getKey(), (string) $availableProduct->getKey(), null, '1.000', 1);
        $manager->addItem((string) $cart->getKey(), (string) $unavailableProduct->getKey(), null, '1.000', 2);

        $checkout = app(SalesCheckoutService::class);
        $result = $checkout->confirm(
            (string) $customer->getKey(),
            (string) $cart->getKey(),
            (string) $address->getKey(),
            'sales-checkout-replay-0001',
        );

        $this->assertDatabaseCount('orders', 1);
        $this->assertSame((string) $availableCooperative->getKey(), $result['ordered_cooperative_ids'][0]);
        $this->assertContains((string) $unavailableCooperative->getKey(), $result['excluded_cooperative_ids']);
        $this->assertDatabaseHas('sales_cart_items', [
            'cart_id' => $cart->getKey(),
            'product_id' => $availableProduct->getKey(),
            'status' => 'ordered',
        ]);
        $this->assertDatabaseHas('sales_cart_items', [
            'cart_id' => $cart->getKey(),
            'product_id' => $unavailableProduct->getKey(),
            'status' => 'active',
        ]);

        $retry = $checkout->confirm(
            (string) $customer->getKey(),
            (string) $cart->getKey(),
            (string) $address->getKey(),
            'sales-checkout-replay-0001',
        );

        $this->assertSame($result['order_id'], $retry['order_id']);
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(1, SalesCart::query()->findOrFail($cart->getKey())->activeItems()->count());
    }

    public function test_reusing_idempotency_key_with_another_address_is_rejected(): void
    {
        $customer = User::factory()->create();
        $firstAddress = Address::factory()->forUser($customer)->create([
            'street' => 'Rue A',
            'city' => 'Casablanca',
        ]);
        $secondAddress = Address::factory()->forUser($customer)->create([
            'street' => 'Rue B',
            'city' => 'Rabat',
        ]);
        [, $product] = $this->productWithStock('5.000');
        $manager = app(SalesCartManager::class);
        $cart = $manager->getOrCreateCustomerCart((string) $customer->getKey());
        $manager->addItem((string) $cart->getKey(), (string) $product->getKey(), null, '1.000', 1);

        $checkout = app(SalesCheckoutService::class);
        $key = 'sales-checkout-context-key-001';
        $first = $checkout->confirm(
            (string) $customer->getKey(),
            (string) $cart->getKey(),
            (string) $firstAddress->getKey(),
            $key,
        );

        try {
            $checkout->confirm(
                (string) $customer->getKey(),
                (string) $cart->getKey(),
                (string) $secondAddress->getKey(),
                $key,
            );
            $this->fail('A key cannot be replayed with a different delivery address.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('idempotency_key', $exception->errors());
            $this->assertDatabaseCount('orders', 1);
            $this->assertSame($first['order_id'], Order::query()->firstOrFail()->getKey());
        }
    }

    public function test_order_creation_failure_keeps_cart_items_and_does_not_write_an_idempotency_receipt(): void
    {
        $customer = User::factory()->create();
        $address = Address::factory()->forUser($customer)->create();
        [, $product] = $this->productWithStock('5.000');
        $manager = app(SalesCartManager::class);
        $cart = $manager->getOrCreateCustomerCart((string) $customer->getKey());
        $manager->addItem((string) $cart->getKey(), (string) $product->getKey(), null, '1.000', 1);

        $this->mock(OrderCheckoutCreator::class, function ($mock): void {
            $mock->shouldReceive('create')
                ->once()
                ->andThrow(new \RuntimeException('Simulated order persistence failure.'));
        });

        try {
            app(SalesCheckoutService::class)->confirm(
                (string) $customer->getKey(),
                (string) $cart->getKey(),
                (string) $address->getKey(),
                'sales-checkout-transaction-failure-01',
            );
            $this->fail('The simulated order failure must be propagated.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated order persistence failure.', $exception->getMessage());
            $this->assertSame(1, $cart->fresh()->activeItems()->count());
            $this->assertDatabaseCount('orders', 0);
            $this->assertDatabaseCount('sales_checkout_attempts', 0);
            $this->assertDatabaseHas('sales_cart_items', [
                'cart_id' => $cart->getKey(),
                'product_id' => $product->getKey(),
                'status' => 'active',
            ]);
        }
    }

    public function test_all_unavailable_cooperatives_leave_the_cart_and_create_no_order(): void
    {
        $customer = User::factory()->create();
        $address = Address::factory()->forUser($customer)->create();
        [, $product] = $this->productWithStock('0.000');

        $manager = app(SalesCartManager::class);
        $cart = $manager->getOrCreateCustomerCart((string) $customer->getKey());
        $manager->addItem((string) $cart->getKey(), (string) $product->getKey(), null, '1.000', 1);

        try {
            app(SalesCheckoutService::class)->confirm(
                (string) $customer->getKey(),
                (string) $cart->getKey(),
                (string) $address->getKey(),
                'sales-checkout-all-unavailable-01',
            );
            $this->fail('Checkout must fail when every cooperative is unavailable.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('orders', 0);
            $this->assertSame(1, $cart->fresh()->activeItems()->count());
        }
    }

    /** @return array{Cooperative, Product} */
    private function productWithStock(string $quantity): array
    {
        $cooperative = Cooperative::factory()->active()->create();
        $unitId = ProductUnit::query()->where('code', 'u')->value('id');
        $product = Product::factory()->forCooperative($cooperative)->create([
            'price' => '12.50',
            'currency' => 'MAD',
            'unit_id' => $unitId,
            'is_active' => true,
        ]);

        InventoryStock::query()->create([
            'cooperative_id' => (string) $cooperative->getKey(),
            'stockable_type' => 'product',
            'stockable_id' => (string) $product->getKey(),
            'unit_code' => 'u',
            'quantity_on_hand' => $quantity,
        ]);

        return [$cooperative, $product];
    }
}
