<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Orders;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authentication\Http\Middleware\EnsureActiveAuthenticationSession;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Customer\Http\Middleware\EnsureCustomerAccount;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Orders\Enums\CooperativeOrderStatus;
use App\Domains\Orders\Enums\OrderItemStatus;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Models\CooperativeOrder;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderItem;
use App\Domains\Orders\Models\OrderStatusHistory;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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

    public function test_customer_can_cancel_only_a_pending_cooperative_suborder_and_totals_are_recalculated(): void
    {
        Notification::fake();

        $customer = $this->customer();
        $order = $this->order($customer, OrderStatus::AWAITING_COOPERATIVES, 'MH-CANCEL-PARTIAL-001');
        $suborder = $order->cooperativeOrders()->firstOrFail();
        $suborder->forceFill([
            'status' => CooperativeOrderStatus::AWAITING_COOPERATIVE,
            'items_subtotal' => '25.00',
            'delivery_fee' => '5.00',
            'grand_total' => '30.00',
        ])->save();

        $remainingCooperative = Cooperative::factory()->active()->create();
        CooperativeOrder::query()->create([
            'order_id' => (string) $order->getKey(),
            'cooperative_id' => (string) $remainingCooperative->getKey(),
            'reference' => 'MH-CANCEL-PARTIAL-001-C2',
            'status' => CooperativeOrderStatus::AWAITING_COOPERATIVE,
            'items_subtotal' => '15.00',
            'delivery_fee' => '8.00',
            'grand_total' => '23.00',
            'response_deadline_at' => now()->addHours(12),
        ]);

        $order->forceFill([
            'items_subtotal' => '40.00',
            'delivery_total' => '13.00',
            'grand_total' => '53.00',
        ])->save();

        $this->actingAs($customer)
            ->from(route('customer.orders.show', $order))
            ->post(route('customer.orders.cooperative-orders.cancel', [$order, $suborder]), [
                'reason' => 'Je souhaite annuler cette coopérative.',
            ])
            ->assertRedirect(route('customer.orders.show', $order));

        $this->assertSame(CooperativeOrderStatus::CANCELLED, $suborder->fresh()->status);
        $this->assertSame(OrderItemStatus::CANCELLED, $suborder->items()->firstOrFail()->fresh()->status);
        $this->assertSame(OrderStatus::PARTIALLY_CANCELLED, $order->fresh()->status);
        $this->assertSame('15.00', $order->fresh()->items_subtotal);
        $this->assertSame('8.00', $order->fresh()->delivery_total);
        $this->assertSame('23.00', $order->fresh()->grand_total);
        $this->assertDatabaseHas('order_status_histories', [
            'subject_type' => 'cooperative_order',
            'subject_id' => $suborder->getKey(),
            'actor_id' => $customer->getKey(),
            'reason' => 'Je souhaite annuler cette coopérative.',
        ]);
        Notification::assertSentTo($customer, \App\Domains\Orders\Notifications\CustomerOrderCancelledNotification::class);
    }

    public function test_customer_cannot_cancel_a_suborder_after_cooperative_confirmation(): void
    {
        $customer = $this->customer();
        $order = $this->order($customer, OrderStatus::CONFIRMED, 'MH-CANCEL-BLOCKED-001');
        $suborder = $order->cooperativeOrders()->firstOrFail();

        $this->actingAs($customer)
            ->from(route('customer.orders.show', $order))
            ->post(route('customer.orders.cooperative-orders.cancel', [$order, $suborder]), [
                'reason' => 'Je souhaite annuler.',
            ])
            ->assertSessionHasErrors('cooperative_order');

        $this->assertSame(CooperativeOrderStatus::CONFIRMED, $suborder->fresh()->status);
    }

    public function test_customer_cannot_cancel_another_customers_suborder(): void
    {
        $customer = $this->customer();
        $owner = $this->customer();
        $order = $this->order($owner, OrderStatus::AWAITING_COOPERATIVES, 'MH-CANCEL-PRIVATE-001');
        $suborder = $order->cooperativeOrders()->firstOrFail();
        $suborder->forceFill(['status' => CooperativeOrderStatus::AWAITING_COOPERATIVE])->save();

        $this->actingAs($customer)
            ->post(route('customer.orders.cooperative-orders.cancel', [$order, $suborder]), [
                'reason' => 'Tentative non autorisée.',
            ])
            ->assertNotFound();

        $this->assertSame(CooperativeOrderStatus::AWAITING_COOPERATIVE, $suborder->fresh()->status);
    }

    public function test_customer_cancellation_requires_a_reason(): void
    {
        $customer = $this->customer();
        $order = $this->order($customer, OrderStatus::AWAITING_COOPERATIVES, 'MH-CANCEL-REASON-001');
        $suborder = $order->cooperativeOrders()->firstOrFail();
        $suborder->forceFill(['status' => CooperativeOrderStatus::AWAITING_COOPERATIVE])->save();

        $this->actingAs($customer)
            ->from(route('customer.orders.show', $order))
            ->post(route('customer.orders.cooperative-orders.cancel', [$order, $suborder]), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertSame(CooperativeOrderStatus::AWAITING_COOPERATIVE, $suborder->fresh()->status);
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
