<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Orders;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authentication\Http\Middleware\EnsureActiveAuthenticationSession;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Inventory\Models\InventoryStock;
use App\Domains\Orders\Enums\CooperativeOrderStatus;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Notifications\CooperativeOrderWorkflowNotification;
use App\Domains\Orders\Services\CooperativeOrderEscalationService;
use App\Domains\Orders\Notifications\OrderReadyForShipmentNotification;
use App\Domains\Orders\Services\OrderCreationService;
use App\Domains\Products\Models\Product;
use App\Domains\Products\Models\ProductUnit;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CooperativeOrderControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([EnsureActiveAuthenticationSession::class]);
        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
        ProductUnit::query()->firstOrCreate(
            ['code' => 'u'],
            ['label' => 'Unité / pièce (u)', 'dimension' => 'count', 'sort_order' => 50, 'is_active' => true],
        );
    }

    public function test_cooperative_admin_sees_only_own_suborders_and_cannot_open_another_cooperative_suborder(): void
    {
        $customer = $this->userWithRole('customer');
        $cooperativeA = Cooperative::factory()->active()->create();
        $cooperativeB = Cooperative::factory()->active()->create();
        $adminA = $this->userWithRole('cooperative_admin', ['cooperative_id' => $cooperativeA->getKey()]);
        $this->userWithRole('cooperative_admin', ['cooperative_id' => $cooperativeB->getKey()]);
        $orderA = $this->makeOrder($customer, $cooperativeA, 'INBOX-OWN-001');
        $orderB = $this->makeOrder($customer, $cooperativeB, 'INBOX-OTHER-001');
        $suborderA = $orderA->cooperativeOrders->firstOrFail();
        $suborderB = $orderB->cooperativeOrders->firstOrFail();

        $this->actingAs($adminA)
            ->get(route('backoffice.orders.cooperative.index'))
            ->assertOk()
            ->assertSee($suborderA->reference)
            ->assertDontSee($suborderB->reference);

        $this->actingAs($adminA)
            ->get(route('backoffice.orders.cooperative.show', $suborderB))
            ->assertNotFound();

        $this->assertSame((string) $cooperativeA->getKey(), (string) $suborderA->cooperative_id);
    }

    public function test_cooperative_admin_can_confirm_and_refuse_own_suborders_with_a_required_reason(): void
    {
        Notification::fake();
        $customer = $this->userWithRole('customer');
        $cooperative = Cooperative::factory()->active()->create();
        $admin = $this->userWithRole('cooperative_admin', ['cooperative_id' => $cooperative->getKey()]);
        $employee = $this->userWithRole('user', ['cooperative_id' => $cooperative->getKey()]);
        $confirmOrder = $this->makeOrder($customer, $cooperative, 'INBOX-CONFIRM-001');
        $refuseOrder = $this->makeOrder($customer, $cooperative, 'INBOX-REFUSE-001');
        $toConfirm = $confirmOrder->cooperativeOrders->firstOrFail();
        $toRefuse = $refuseOrder->cooperativeOrders->firstOrFail();

        $this->actingAs($admin)
            ->post(route('backoffice.orders.cooperative.confirm', $toConfirm))
            ->assertRedirect();

        $this->assertSame(CooperativeOrderStatus::CONFIRMED, $toConfirm->fresh()->status);
        Notification::assertSentTo($employee, CooperativeOrderWorkflowNotification::class);
        $this->assertDatabaseHas('inventory_reservations', [
            'cooperative_order_id' => $toConfirm->getKey(),
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->from(route('backoffice.orders.cooperative.show', $toRefuse))
            ->post(route('backoffice.orders.cooperative.refuse', $toRefuse), ['reason' => 'Stock indisponible après contrôle.'])
            ->assertRedirect();

        $this->assertSame(CooperativeOrderStatus::REFUSED, $toRefuse->fresh()->status);
        $this->assertSame('Stock indisponible après contrôle.', $toRefuse->fresh()->refusal_reason);

        $this->actingAs($admin)
            ->from(route('backoffice.orders.cooperative.show', $toRefuse))
            ->post(route('backoffice.orders.cooperative.refuse', $toRefuse), ['reason' => ''])
            ->assertSessionHasErrors('reason');
    }

    public function test_super_admin_can_access_only_assigned_cooperatives_and_root_can_access_all(): void
    {
        $customer = $this->userWithRole('customer');
        $cooperativeA = Cooperative::factory()->active()->create();
        $cooperativeB = Cooperative::factory()->active()->create();
        $superAdmin = $this->userWithRole('super_admin');
        $superAdmin->cooperatives()->attach($cooperativeA->getKey());
        $root = $this->userWithRole('super_admin_root');
        $orderA = $this->makeOrder($customer, $cooperativeA, 'INBOX-ASSIGNED-001');
        $orderB = $this->makeOrder($customer, $cooperativeB, 'INBOX-UNASSIGNED-001');

        $this->actingAs($superAdmin)
            ->get(route('backoffice.orders.cooperative.index'))
            ->assertOk()
            ->assertSee($orderA->cooperativeOrders->firstOrFail()->reference)
            ->assertDontSee($orderB->cooperativeOrders->firstOrFail()->reference);

        $this->actingAs($root)
            ->get(route('root.backoffice.orders.cooperative.index'))
            ->assertOk()
            ->assertSee($orderA->cooperativeOrders->firstOrFail()->reference)
            ->assertSee($orderB->cooperativeOrders->firstOrFail()->reference);
    }

    public function test_root_can_extend_cooperative_response_deadline(): void
    {
        $customer = $this->userWithRole('customer');
        $cooperative = Cooperative::factory()->active()->create();
        $root = $this->userWithRole('super_admin_root');
        $order = $this->makeOrder($customer, $cooperative, 'INBOX-ADMIN-001');
        $suborder = $order->cooperativeOrders->firstOrFail();
        $newDeadline = now()->addHours(20)->startOfMinute();

        $this->actingAs($root)
            ->post(route('root.backoffice.orders.cooperative.extend-deadline', $suborder), [
                'response_deadline_at' => $newDeadline->format('Y-m-d H:i:s'),
                'reason' => 'Délai supplémentaire justifié.',
            ])
            ->assertRedirect();

        $this->assertEquals($newDeadline->timestamp, $suborder->fresh()->response_deadline_at->timestamp);

        // Root substitution is tested separately at the H+24 escalation threshold.
    }

    public function test_assigned_super_admin_can_make_a_reasoned_substitute_decision_after_h12(): void
    {
        $customer = $this->userWithRole('customer');
        $cooperative = Cooperative::factory()->active()->create();
        $superAdmin = $this->userWithRole('super_admin');
        $superAdmin->cooperatives()->attach($cooperative->getKey());
        $order = $this->makeOrder($customer, $cooperative, 'INBOX-H12-ADMIN-001');
        $suborder = $order->cooperativeOrders->firstOrFail();

        $this->actingAs($superAdmin)
            ->post(route('backoffice.orders.cooperative.confirm', $suborder))
            ->assertForbidden();

        $suborder->forceFill([
            'created_at' => now()->subHours(13),
            'response_deadline_at' => now()->subHour(),
        ])->save();

        $this->actingAs($superAdmin)
            ->post(route('backoffice.orders.cooperative.administrative-decision', $suborder), [
                'accept' => false,
                'reason' => 'Aucune réponse de la coopérative après le délai.',
            ])
            ->assertRedirect();

        $this->assertSame(CooperativeOrderStatus::REFUSED, $suborder->fresh()->status);
        $this->assertSame('Aucune réponse de la coopérative après le délai.', $suborder->fresh()->substitution_reason);
        $this->assertSame((string) $superAdmin->getKey(), (string) $suborder->fresh()->substituted_by_admin_id);
    }

    public function test_root_cannot_make_a_substitute_decision_before_h24(): void
    {
        $customer = $this->userWithRole('customer');
        $root = $this->userWithRole('super_admin_root');
        $cooperative = Cooperative::factory()->active()->create();
        $order = $this->makeOrder($customer, $cooperative, 'INBOX-ROOT-BEFORE-H24');
        $suborder = $order->cooperativeOrders->firstOrFail();

        $this->actingAs($root)
            ->post(route('root.backoffice.orders.cooperative.administrative-decision', $suborder), [
                'accept' => false,
                'reason' => 'Décision prématurée.',
            ])
            ->assertSessionHasErrors('cooperative_order');

        $this->assertSame(CooperativeOrderStatus::AWAITING_COOPERATIVE, $suborder->fresh()->status);
    }

    public function test_root_can_make_a_reasoned_substitute_decision_at_h24(): void
    {
        $customer = $this->userWithRole('customer');
        $root = $this->userWithRole('super_admin_root');
        $cooperative = Cooperative::factory()->active()->create();
        $order = $this->makeOrder($customer, $cooperative, 'INBOX-ROOT-H24');
        $suborder = $order->cooperativeOrders->firstOrFail();
        $suborder->forceFill([
            'created_at' => now()->subHours(25),
            'response_deadline_at' => now()->subHours(13),
        ])->save();

        $this->actingAs($root)
            ->post(route('root.backoffice.orders.cooperative.administrative-decision', $suborder), [
                'accept' => false,
                'reason' => 'Décision root après escalade H+24.',
            ])
            ->assertRedirect();

        $this->assertSame(CooperativeOrderStatus::REFUSED, $suborder->fresh()->status);
        $this->assertSame('Décision root après escalade H+24.', $suborder->fresh()->substitution_reason);
    }

    public function test_overdue_escalations_notify_super_admin_at_h12_and_root_at_h24_without_expiring_suborder(): void
    {
        Notification::fake();

        $customer = $this->userWithRole('customer');
        $cooperative = Cooperative::factory()->active()->create();
        $superAdmin = $this->userWithRole('super_admin');
        $superAdmin->cooperatives()->attach($cooperative->getKey());
        $root = $this->userWithRole('super_admin_root');
        $order = $this->makeOrder($customer, $cooperative, 'INBOX-ESCALATION-H24');
        $suborder = $order->cooperativeOrders->firstOrFail();
        $suborder->forceFill([
            'created_at' => now()->subHours(25),
            'response_deadline_at' => now()->subHours(13),
        ])->save();

        $processed = app(CooperativeOrderEscalationService::class)->escalateOverdue();

        $this->assertSame(1, $processed);
        $suborder->refresh();
        $this->assertSame(CooperativeOrderStatus::AWAITING_COOPERATIVE, $suborder->status);
        $this->assertNotNull($suborder->response_overdue_at);
        $this->assertNotNull($suborder->super_admin_notified_at);
        $this->assertNotNull($suborder->root_escalated_at);
        Notification::assertSentTo($superAdmin, CooperativeOrderWorkflowNotification::class);
        Notification::assertSentTo($root, CooperativeOrderWorkflowNotification::class);
        $this->assertDatabaseHas('order_status_histories', [
            'subject_type' => 'cooperative_order',
            'subject_id' => $suborder->getKey(),
            'to_status' => CooperativeOrderStatus::AWAITING_COOPERATIVE->value,
        ]);

        $this->assertSame(0, app(CooperativeOrderEscalationService::class)->escalateOverdue());
    }

    public function test_cooperative_user_can_prepare_only_own_confirmed_suborders_and_customer_is_notified_when_ready(): void
    {
        Notification::fake();

        $customer = $this->userWithRole('customer');
        $cooperative = Cooperative::factory()->active()->create();
        $employee = $this->userWithRole('user', ['cooperative_id' => $cooperative->getKey()]);
        $cooperativeAdmin = $this->userWithRole('cooperative_admin', ['cooperative_id' => $cooperative->getKey()]);
        $otherCooperative = Cooperative::factory()->active()->create();
        $otherEmployee = $this->userWithRole('user', ['cooperative_id' => $otherCooperative->getKey()]);

        $order = $this->makeOrder($customer, $cooperative, 'INBOX-PREPARATION-001');
        $suborder = $order->cooperativeOrders->firstOrFail();
        Notification::assertSentTo($cooperativeAdmin, CooperativeOrderWorkflowNotification::class);
        $this->assertSame('cash_on_delivery', $order->fresh()->payment_method);
        $suborder->forceFill(['status' => CooperativeOrderStatus::CONFIRMED, 'confirmed_at' => now()])->save();

        $this->actingAs($employee)
            ->get(route('backoffice.orders.cooperative.show', $suborder))
            ->assertOk()
            ->assertSee('Commencer la préparation');

        $this->actingAs($otherEmployee)
            ->get(route('backoffice.orders.cooperative.show', $suborder))
            ->assertNotFound();

        $this->actingAs($employee)
            ->post(route('backoffice.orders.cooperative.confirm', $suborder))
            ->assertForbidden();

        $this->actingAs($employee)
            ->post(route('backoffice.orders.cooperative.refuse', $suborder), ['reason' => 'Refus interdit pour un employé.'])
            ->assertForbidden();

        $this->actingAs($employee)
            ->post(route('backoffice.orders.cooperative.start-preparation', $suborder))
            ->assertRedirect();

        $this->assertSame(CooperativeOrderStatus::PREPARING, $suborder->fresh()->status);

        $this->actingAs($employee)
            ->post(route('backoffice.orders.cooperative.ready-for-shipment', $suborder))
            ->assertRedirect();

        $this->assertSame(CooperativeOrderStatus::READY_FOR_SHIPMENT, $suborder->fresh()->status);
        $this->assertDatabaseHas('order_status_histories', [
            'subject_type' => 'cooperative_order',
            'subject_id' => $suborder->getKey(),
            'to_status' => CooperativeOrderStatus::READY_FOR_SHIPMENT->value,
            'actor_id' => $employee->getKey(),
        ]);

        Notification::assertSentTo($customer, OrderReadyForShipmentNotification::class);
    }

    private function makeOrder(User $customer, Cooperative $cooperative, string $key): Order
    {
        $product = Product::factory()->forCooperative($cooperative)->create([
            'price' => '20.00',
            'currency' => 'MAD',
            'unit_id' => ProductUnit::query()->where('code', 'u')->value('id'),
        ]);
        InventoryStock::query()->create([
            'cooperative_id' => (string) $cooperative->getKey(),
            'stockable_type' => 'product',
            'stockable_id' => (string) $product->getKey(),
            'unit_code' => 'u',
            'quantity_on_hand' => '10.000',
        ]);

        return app(OrderCreationService::class)->createFromCheckout($customer, [
            'idempotency_key' => $key,
            'delivery_address_snapshot' => ['city' => 'Casablanca', 'country' => 'Maroc'],
            'delivery_quotes' => [[
                'quote_reference' => (string) Str::ulid(),
                'cooperative_id' => (string) $cooperative->getKey(),
                'amount' => '40.00',
                'currency' => 'MAD',
                'tariff_code' => 'TEST_INITIAL_FLAT',
                'issued_at' => now()->toIso8601String(),
                'expires_at' => now()->addMinutes(30)->toIso8601String(),
            ]],
            'items' => [['product_id' => (string) $product->getKey(), 'quantity' => '1']],
        ]);
    }

    private function userWithRole(string $slug, array $attributes = []): User
    {
        $role = Role::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => $slug, 'is_active' => true],
        );
        $user = User::factory()->create(array_merge([
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
        ], $attributes));
        $user->roles()->attach($role->getKey());

        return $user;
    }
}
