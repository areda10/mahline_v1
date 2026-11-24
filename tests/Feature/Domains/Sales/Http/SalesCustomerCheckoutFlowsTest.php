<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Sales\Http;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authentication\Http\Middleware\EnsureActiveAuthenticationSession;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Inventory\Models\InventoryStock;
use App\Domains\Localization\Models\Address;
use App\Domains\Orders\Models\Order;
use App\Domains\Products\Models\Product;
use App\Domains\Products\Models\ProductUnit;
use App\Domains\Sales\Contracts\SalesCartManager;
use App\Domains\Sales\Models\SalesCart;
use Database\Seeders\ProductUnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SalesCustomerCheckoutFlowsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Sec-Fetch-Site', 'same-origin');
        $this->seed(ProductUnitSeeder::class);
    }

    public function test_customer_can_confirm_the_whole_persistent_cart_with_delivery_per_cooperative(): void
    {
        $customer = $this->checkoutCustomer();
        $address = Address::factory()->forUser($customer)->create();

        [, $firstProduct] = $this->productWithStock('4.000');
        [, $secondProduct] = $this->productWithStock('5.000');

        $manager = app(SalesCartManager::class);
        $cart = $manager->getOrCreateCustomerCart((string) $customer->getKey());
        $manager->addItem((string) $cart->getKey(), (string) $firstProduct->getKey(), null, '1.000', 1);
        $manager->addItem((string) $cart->getKey(), (string) $secondProduct->getKey(), null, '2.000', 2);

        $this->actingAs($customer)
            ->withoutMiddleware(EnsureActiveAuthenticationSession::class)
            ->get(route('sales.cart.checkout.page'))
            ->assertOk()
            ->assertViewIs('storefront.checkout')
            ->assertViewHas('directCartId', null);

        $this->actingAs($customer)
            ->withoutMiddleware(EnsureActiveAuthenticationSession::class)
            ->getJson(route('sales.cart.checkout.preview'))
            ->assertOk()
            ->assertJsonPath('data.checkout_allowed', true)
            ->assertJsonCount(2, 'data.groups');

        $response = $this->actingAs($customer)
            ->withoutMiddleware(EnsureActiveAuthenticationSession::class)
            ->postJson(route('sales.cart.checkout.confirm'), [
                'delivery_address_id' => (string) $address->getKey(),
                'idempotency_key' => 'feature-cart-checkout-flow-0001',
            ])
            ->assertOk()
            ->assertJsonPath('data.excluded_cooperative_ids', []);

        $orderId = $response->json('data.order_id');

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'delivery_total' => '80.00',
        ]);
        $this->assertDatabaseCount('order_items', 2);
        $this->assertDatabaseHas('sales_cart_items', [
            'cart_id' => $cart->getKey(),
            'product_id' => $firstProduct->getKey(),
            'status' => 'ordered',
        ]);
        $this->assertDatabaseHas('sales_cart_items', [
            'cart_id' => $cart->getKey(),
            'product_id' => $secondProduct->getKey(),
            'status' => 'ordered',
        ]);
        $this->assertSame(0, $cart->fresh()->activeItems()->count());
    }

    public function test_direct_checkout_orders_only_the_selected_product_and_never_changes_the_customer_cart(): void
    {
        $customer = $this->checkoutCustomer();
        $address = Address::factory()->forUser($customer)->create();

        [, $cartProduct] = $this->productWithStock('5.000');
        [, $directProduct] = $this->productWithStock('5.000');

        $manager = app(SalesCartManager::class);
        $customerCart = $manager->getOrCreateCustomerCart((string) $customer->getKey());
        $manager->addItem((string) $customerCart->getKey(), (string) $cartProduct->getKey(), null, '3.000', 1);

        $start = $this->actingAs($customer)
            ->withoutMiddleware(EnsureActiveAuthenticationSession::class)
            ->post(route('sales.cart.checkout.direct.start'), [
                'product_id' => (string) $directProduct->getKey(),
                'quantity' => '2.000',
            ])
            ->assertRedirect();

        $location = (string) $start->headers->get('Location');
        $this->assertMatchesRegularExpression(
            '#/cart/checkout/direct/[0-9A-HJKMNP-TV-Z]{26}$#',
            parse_url($location, PHP_URL_PATH) ?: '',
        );
        preg_match('#/cart/checkout/direct/([0-9A-HJKMNP-TV-Z]{26})$#', $location, $matches);
        $directCartId = $matches[1] ?? null;
        $this->assertIsString($directCartId);

        $directCart = SalesCart::query()->findOrFail($directCartId);
        $this->assertNull($directCart->customer_id);
        $this->assertNotEmpty($directCart->guest_token_hash);
        $this->assertSame(1, $directCart->activeItems()->count());
        $this->assertDatabaseHas('sales_cart_items', [
            'cart_id' => $directCartId,
            'product_id' => $directProduct->getKey(),
            'quantity' => '2.000',
            'status' => 'active',
        ]);

        $this->actingAs($customer)
            ->withoutMiddleware(EnsureActiveAuthenticationSession::class)
            ->get(route('sales.cart.checkout.direct', ['cartId' => $directCartId]))
            ->assertOk()
            ->assertViewIs('storefront.checkout')
            ->assertViewHas('directCartId', $directCartId);

        $this->actingAs($customer)
            ->withoutMiddleware(EnsureActiveAuthenticationSession::class)
            ->getJson(route('sales.cart.checkout.preview', ['direct_cart_id' => $directCartId]))
            ->assertOk()
            ->assertJsonPath('data.checkout_allowed', true)
            ->assertJsonPath('data.groups.0.items.0.product.product_id', (string) $directProduct->getKey());

        $response = $this->actingAs($customer)
            ->withoutMiddleware(EnsureActiveAuthenticationSession::class)
            ->postJson(route('sales.cart.checkout.confirm'), [
                'direct_cart_id' => $directCartId,
                'delivery_address_id' => (string) $address->getKey(),
                'idempotency_key' => 'feature-direct-checkout-flow-0001',
            ])
            ->assertOk();

        $orderId = $response->json('data.order_id');

        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(1, OrderItem::query()
            ->whereHas('cooperativeOrder', static fn ($query) => $query->where('order_id', $orderId))
            ->where('product_id', $directProduct->getKey())
            ->count());
        $this->assertSame(0, OrderItem::query()
            ->whereHas('cooperativeOrder', static fn ($query) => $query->where('order_id', $orderId))
            ->where('product_id', $cartProduct->getKey())
            ->count());

        $this->assertSame(1, $customerCart->fresh()->activeItems()->count());
        $this->assertDatabaseHas('sales_cart_items', [
            'cart_id' => $customerCart->getKey(),
            'product_id' => $cartProduct->getKey(),
            'quantity' => '3.000',
            'status' => 'active',
        ]);
        $this->assertSame(0, $directCart->fresh()->activeItems()->count());
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
        $product = Product::factory()->forCooperative($cooperative)->create([
            'price' => '12.50',
            'currency' => 'MAD',
            'unit_id' => ProductUnit::query()->where('code', 'u')->value('id'),
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
