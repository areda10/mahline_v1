<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Orders;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Orders\Enums\CooperativeOrderStatus;
use App\Domains\Orders\Enums\OrderItemStatus;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Models\CooperativeOrder;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderItem;
use App\Domains\Orders\Models\OrderStatusHistory;
use App\Domains\Identity\Authentication\Http\Middleware\EnsureActiveAuthenticationSession;
use App\Domains\Identity\Customer\Http\Middleware\EnsureCustomerAccount;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CustomerOrderHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->withoutMiddleware([
            EnsureActiveAuthenticationSession::class,
            EnsureCustomerAccount::class,
        ]);
    }

    public function test_customer_can_list_only_their_orders_and_filter_by_status_and_reference(): void
    {
        $customer = $this->customer();
        $otherCustomer = $this->customer();
        $visible = $this->order($customer, OrderStatus::CONFIRMED, 'MH-CLIENT-001');
        $this->order($customer, OrderStatus::CANCELLED, 'MH-CLIENT-002');
        $this->order($otherCustomer, OrderStatus::CONFIRMED, 'MH-PRIVATE-999');

        $this->actingAs($customer)
            ->get(route('customer.orders.index', ['q' => 'MH-CLIENT-001', 'status' => 'confirmed']))
            ->assertOk()
            ->assertSee($visible->reference)
            ->assertDontSee('MH-CLIENT-002')
            ->assertDontSee('MH-PRIVATE-999');
    }

    public function test_customer_can_view_order_details_and_status_timeline(): void
    {
        $customer = $this->customer();
        $order = $this->order($customer, OrderStatus::CONFIRMED, 'MH-DETAIL-001');
        $suborder = $order->cooperativeOrders()->firstOrFail();

        OrderStatusHistory::query()->create([
            'subject_type' => 'order',
            'subject_id' => (string) $order->getKey(),
            'from_status' => OrderStatus::AWAITING_COOPERATIVES->value,
            'to_status' => OrderStatus::CONFIRMED->value,
            'actor_id' => (string) $customer->getKey(),
            'actor_role' => 'customer',
            'reason' => 'Commande confirmée',
            'metadata' => null,
            'occurred_at' => now(),
        ]);

        $this->actingAs($customer)
            ->get(route('customer.orders.show', $order))
            ->assertOk()
            ->assertSee('MH-DETAIL-001')
            ->assertSee($suborder->reference)
            ->assertSee('Commande confirmée')
            ->assertSee('Article de test');
    }

    public function test_customer_cannot_view_another_customers_order(): void
    {
        $customer = $this->customer();
        $owner = $this->customer();
        $order = $this->order($owner, OrderStatus::CONFIRMED, 'MH-PRIVATE-001');

        $this->actingAs($customer)
            ->get(route('customer.orders.show', $order))
            ->assertNotFound();
    }

    private function customer(): User
    {
        $role = Role::query()->where('slug', 'customer')->firstOrFail();
        $customer = User::factory()->create([
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
        ]);
        $customer->roles()->attach($role->getKey());

        return $customer;
    }

    private function order(User $customer, OrderStatus $status, string $reference): Order
    {
        $cooperative = Cooperative::factory()->active()->create();
        $order = Order::query()->create([
            'customer_id' => (string) $customer->getKey(),
            'reference' => $reference,
            'status' => $status,
            'currency' => 'MAD',
            'items_subtotal' => '25.00',
            'delivery_total' => '0.00',
            'grand_total' => '25.00',
            'delivery_address_snapshot' => [
                'address' => 'Avenue Hassan II',
                'city' => 'Casablanca',
                'country' => 'Maroc',
            ],
            'placed_at' => now(),
        ]);

        $suborder = CooperativeOrder::query()->create([
            'order_id' => (string) $order->getKey(),
            'cooperative_id' => (string) $cooperative->getKey(),
            'reference' => $reference.'-C1',
            'status' => CooperativeOrderStatus::CONFIRMED,
            'items_subtotal' => '25.00',
            'delivery_fee' => '0.00',
            'grand_total' => '25.00',
            'response_deadline_at' => now()->addHours(12),
        ]);

        OrderItem::query()->create([
            'cooperative_order_id' => (string) $suborder->getKey(),
            'product_id' => null,
            'product_variant_id' => null,
            'product_snapshot' => [
                'product_name' => 'Article de test',
                'variant_label' => null,
                'unit_label' => 'Unité',
            ],
            'quantity' => '1.000',
            'unit' => 'u',
            'unit_price' => '25.00',
            'tax_rate' => '0.000',
            'tax_amount' => '0.00',
            'line_subtotal' => '25.00',
            'line_total' => '25.00',
            'status' => OrderItemStatus::ACTIVE,
        ]);

        return $order;
    }
}
