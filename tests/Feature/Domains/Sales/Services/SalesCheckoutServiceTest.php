<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Sales\Services;

use App\Domains\Cooperatives\Enums\CooperativeStatus;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authentication\Http\Middleware\EnsureActiveAuthenticationSession;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Inventory\Models\InventoryStock;
use App\Domains\Localization\Models\Address;
use App\Domains\Orders\Contracts\OrderCheckoutCreator;
use App\Domains\Orders\Models\Order;
use App\Domains\Products\Models\Product;
use App\Domains\Products\Models\ProductUnit;
use App\Domains\Products\Models\ProductVariant;
use App\Domains\Sales\Contracts\SalesCartManager;
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

        // Match the same-origin Fetch Metadata header sent by browsers.
        $this->withHeader('Sec-Fetch-Site', 'same-origin');
        $this->seed(ProductUnitSeeder::class);
    }

    public function test_confirmation_creates_partial_order_for_stock_available_cooperatives_and_preserves_excluded_lines(): void
    {
        $customer = User::factory()->create();
        $address = Address::factory()->forUser($customer)->create();
        [$availableCooperative, $availableProduct] = $this->productWithStock('4.000');
        [$unavailableCooperative, $unavailableProduct] = $this->productWithStock('0.000');

        $manager = app(SalesCartManager::class);
        $cart = $manager->getOrCreateCustomerCart((string) $customer->getKey());
        $manager->addItem((string) $cart->getKey(), (string) $availableProduct->getKey(), null, '1.000', 1);
        $manager->addItem((string) $cart->getKey(), (string) $unavailableProduct->getKey(), null, '1.000', 2);

        $response = app(SalesCheckoutService::class)->confirm(
            (string) $customer->getKey(),
            (string) $cart->getKey(),
            (string) $address->getKey(),
            'sales-checkout-partial-stock-0001',
        );

        $this->assertSame([(string) $availableCooperative->getKey()], $response['ordered_cooperative_ids']);
        $this->assertSame([(string) $unavailableCooperative->getKey()], $response['excluded_cooperative_ids']);
        $this->assertDatabaseCount('orders', 1);

        $order = Order::query()->with('cooperativeOrders.items')->findOrFail($response['order_id']);
        $this->assertCount(1, $order->cooperativeOrders);
        $this->assertSame('12.50', $order->items_subtotal);
        $this->assertSame('40.00', $order->delivery_total);
        $this->assertCount(1, $order->cooperativeOrders->first()->items);

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
        $this->assertSame(1, $cart->fresh()->activeItems()->count());
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
            'is_primary' => false,
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

    public function test_checkout_preview_route_returns_live_cooperative_availability(): void
    {
        $customer = $this->checkoutCustomer();
        [, $product] = $this->productWithStock('3.000');
        $manager = app(SalesCartManager::class);
        $cart = $manager->getOrCreateCustomerCart((string) $customer->getKey());
        $manager->addItem((string) $cart->getKey(), (string) $product->getKey(), null, '1.000', 1);

        $this->actingAs($customer)
            ->withoutMiddleware(EnsureActiveAuthenticationSession::class)
            ->getJson(route('sales.cart.checkout.preview'))
            ->assertOk()
            ->assertJsonPath('data.available_cooperative_ids.0', (string) $product->cooperative_id)
            ->assertJsonPath('data.unavailable_cooperative_ids', []);
    }

    public function test_checkout_confirm_route_creates_partial_order_and_keeps_excluded_lines_in_the_cart(): void
    {
        $customer = $this->checkoutCustomer();
        $address = Address::factory()->forUser($customer)->create();
        [$availableCooperative, $availableProduct] = $this->productWithStock('3.000');
        [$unavailableCooperative, $unavailableProduct] = $this->productWithStock('0.000');
        $manager = app(SalesCartManager::class);
        $cart = $manager->getOrCreateCustomerCart((string) $customer->getKey());
        $manager->addItem((string) $cart->getKey(), (string) $availableProduct->getKey(), null, '1.000', 1);
        $manager->addItem((string) $cart->getKey(), (string) $unavailableProduct->getKey(), null, '1.000', 2);

        $response = $this->actingAs($customer)
            ->withoutMiddleware(EnsureActiveAuthenticationSession::class)
            ->postJson(route('sales.cart.checkout.confirm'), [
                'delivery_address_id' => (string) $address->getKey(),
                'idempotency_key' => 'http-checkout-partial-stock-0001',
            ])
            ->assertOk()
            ->assertJsonPath('data.ordered_cooperative_ids.0', (string) $availableCooperative->getKey())
            ->assertJsonPath('data.excluded_cooperative_ids.0', (string) $unavailableCooperative->getKey());

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
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
        $this->assertSame(1, $cart->fresh()->activeItems()->count());
    }

    public function test_checkout_preview_allows_available_cooperatives_and_displays_stock_exclusions(): void
    {
        $customer = $this->checkoutCustomer();
        [$availableCooperative, $availableProduct] = $this->productWithStock('3.000');
        [$unavailableCooperative] = $this->productWithStock('0.000');
        [, $unavailableProduct] = $this->productWithStock('0.000');

        $manager = app(SalesCartManager::class);
        $cart = $manager->getOrCreateCustomerCart((string) $customer->getKey());
        $manager->addItem((string) $cart->getKey(), (string) $availableProduct->getKey(), null, '1.000', 1);
        $manager->addItem((string) $cart->getKey(), (string) $unavailableProduct->getKey(), null, '1.000', 2);

        $this->actingAs($customer)
            ->withoutMiddleware(EnsureActiveAuthenticationSession::class)
            ->getJson(route('sales.cart.checkout.preview'))
            ->assertOk()
            ->assertJsonPath('data.checkout_allowed', true)
            ->assertJsonPath('data.available_cooperative_ids.0', (string) $availableCooperative->getKey())
            ->assertJsonPath('data.excluded_cooperative_ids.0', (string) $unavailableCooperative->getKey())
            ->assertJsonCount(1, 'data.groups')
            ->assertJsonCount(1, 'data.excluded_groups');
    }

    public function test_price_increase_after_preview_requires_new_acceptance_and_preserves_cart(): void
    {
        $customer = $this->checkoutCustomer();
        $address = Address::factory()->forUser($customer)->create();
        [, $product] = $this->productWithStock('5.000');
        $manager = app(SalesCartManager::class);
        $cart = $manager->getOrCreateCustomerCart((string) $customer->getKey());
        $manager->addItem((string) $cart->getKey(), (string) $product->getKey(), null, '1.000', 1);

        $this->actingAs($customer)
            ->withoutMiddleware(EnsureActiveAuthenticationSession::class)
            ->getJson(route('sales.cart.checkout.preview'))
            ->assertOk();

        $product->forceFill(['price' => '15.00'])->save();

        $this->actingAs($customer)
            ->withoutMiddleware(EnsureActiveAuthenticationSession::class)
            ->postJson(route('sales.cart.checkout.confirm'), [
                'delivery_address_id' => (string) $address->getKey(),
                'idempotency_key' => 'http-price-increase-recheck-0001',
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(1, $cart->fresh()->activeItems()->count());
        $this->assertDatabaseHas('sales_cart_items', [
            'cart_id' => $cart->getKey(),
            'product_id' => $product->getKey(),
            'status' => 'active',
        ]);
    }

    public function test_price_decrease_after_preview_is_applied_automatically_at_confirmation(): void
    {
        $customer = $this->checkoutCustomer();
        $address = Address::factory()->forUser($customer)->create();
        [, $product] = $this->productWithStock('5.000');
        $manager = app(SalesCartManager::class);
        $cart = $manager->getOrCreateCustomerCart((string) $customer->getKey());
        $manager->addItem((string) $cart->getKey(), (string) $product->getKey(), null, '1.000', 1);

        $this->actingAs($customer)
            ->withoutMiddleware(EnsureActiveAuthenticationSession::class)
            ->getJson(route('sales.cart.checkout.preview'))
            ->assertOk();

        $product->forceFill(['price' => '10.00'])->save();

        $response = $this->actingAs($customer)
            ->withoutMiddleware(EnsureActiveAuthenticationSession::class)
            ->postJson(route('sales.cart.checkout.confirm'), [
                'delivery_address_id' => (string) $address->getKey(),
                'idempotency_key' => 'http-price-decrease-recheck-0001',
            ])
            ->assertOk();

        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->getKey(),
            'unit_price' => '10.00',
        ]);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_product_and_variant_deactivation_between_preview_and_confirmation_blocks_checkout(): void
    {
        $customer = $this->checkoutCustomer();
        $address = Address::factory()->forUser($customer)->create();
        [$cooperative, $product] = $this->productWithStock('5.000');

        $variantProduct = Product::factory()->forCooperative($cooperative)->create([
            'price' => '18.00',
            'currency' => 'MAD',
            'unit_id' => ProductUnit::query()->where('code', 'u')->value('id'),
            'is_active' => true,
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $variantProduct->getKey(),
            'price' => '19.00',
            'unit_id' => ProductUnit::query()->where('code', 'ml')->value('id'),
            'is_active' => true,
        ]);
        InventoryStock::query()->create([
            'cooperative_id' => (string) $cooperative->getKey(),
            'stockable_type' => 'variant',
            'stockable_id' => (string) $variant->getKey(),
            'unit_code' => 'ml',
            'quantity_on_hand' => '5.000',
        ]);

        $manager = app(SalesCartManager::class);
        $cart = $manager->getOrCreateCustomerCart((string) $customer->getKey());
        $manager->addItem((string) $cart->getKey(), (string) $product->getKey(), null, '1.000', 1);
        $manager->addItem((string) $cart->getKey(), (string) $variantProduct->getKey(), (string) $variant->getKey(), '1.000', 2);

        $this->actingAs($customer)
            ->withoutMiddleware(EnsureActiveAuthenticationSession::class)
            ->getJson(route('sales.cart.checkout.preview'))
            ->assertOk();

        $product->forceFill(['is_active' => false])->save();
        $variant->forceFill(['is_active' => false])->save();

        $this->actingAs($customer)
            ->withoutMiddleware(EnsureActiveAuthenticationSession::class)
            ->postJson(route('sales.cart.checkout.confirm'), [
                'delivery_address_id' => (string) $address->getKey(),
                'idempotency_key' => 'http-product-variant-status-0001',
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(2, $cart->fresh()->activeItems()->count());
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

    public function test_http_confirmation_failure_rolls_back_order_receipt_and_cart_consumption(): void
    {
        $customer = $this->checkoutCustomer();
        $address = Address::factory()->forUser($customer)->create();
        [, $product] = $this->productWithStock('5.000');
        $manager = app(SalesCartManager::class);
        $cart = $manager->getOrCreateCustomerCart((string) $customer->getKey());
        $manager->addItem((string) $cart->getKey(), (string) $product->getKey(), null, '1.000', 1);

        $this->mock(OrderCheckoutCreator::class, function ($mock): void {
            $mock->shouldReceive('create')
                ->once()
                ->andThrow(new \RuntimeException('HTTP checkout persistence failure.'));
        });

        try {
            $this->withoutExceptionHandling()
                ->actingAs($customer)
                ->withoutMiddleware(EnsureActiveAuthenticationSession::class)
                ->postJson(route('sales.cart.checkout.confirm'), [
                    'delivery_address_id' => (string) $address->getKey(),
                    'idempotency_key' => 'http-checkout-transaction-failure-01',
                ]);

            $this->fail('The simulated persistence error must propagate through the HTTP route.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('HTTP checkout persistence failure.', $exception->getMessage());
        }

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('sales_checkout_attempts', 0);
        $this->assertSame(1, $cart->fresh()->activeItems()->count());
        $this->assertDatabaseHas('sales_cart_items', [
            'cart_id' => $cart->getKey(),
            'product_id' => $product->getKey(),
            'status' => 'active',
        ]);
    }

    public function test_inactive_cooperative_blocks_the_entire_multi_cooperative_checkout(): void
    {
        $customer = User::factory()->create();
        $address = Address::factory()->forUser($customer)->create();
        [, $availableProduct] = $this->productWithStock('5.000');
        [$inactiveCooperative, $inactiveProduct] = $this->productWithStock('5.000');

        $manager = app(SalesCartManager::class);
        $cart = $manager->getOrCreateCustomerCart((string) $customer->getKey());
        $manager->addItem((string) $cart->getKey(), (string) $availableProduct->getKey(), null, '1.000', 1);
        $manager->addItem((string) $cart->getKey(), (string) $inactiveProduct->getKey(), null, '1.000', 2);

        $inactiveCooperative->forceFill(['status' => CooperativeStatus::INACTIVE])->save();

        try {
            app(SalesCheckoutService::class)->confirm(
                (string) $customer->getKey(),
                (string) $cart->getKey(),
                (string) $address->getKey(),
                'sales-inactive-cooperative-block-001',
            );
            $this->fail('An inactive cooperative must block the complete multi-cooperative checkout.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('cooperatives', $exception->errors());
            $this->assertDatabaseCount('orders', 0);
            $this->assertSame(2, $cart->fresh()->activeItems()->count());
            $this->assertDatabaseHas('sales_cart_items', [
                'cart_id' => $cart->getKey(),
                'product_id' => $availableProduct->getKey(),
                'status' => 'active',
            ]);
            $this->assertDatabaseHas('sales_cart_items', [
                'cart_id' => $cart->getKey(),
                'product_id' => $inactiveProduct->getKey(),
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

    private function checkoutCustomer(): User
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
