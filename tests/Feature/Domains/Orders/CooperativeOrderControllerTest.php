<?php

declare(strict_types=1);

namespace Tests\\Feature\\Domains\\Orders;

use App\\Domains\\Cooperatives\\Models\\Cooperative;
use App\\Domains\\Identity\\Authorization\\Models\\Role;
use App\\Domains\\Identity\\Enums\\UserStatus;
use App\\Domains\\Identity\\Users\\Models\\User;
use App\\Domains\\Inventory\\Models\\InventoryStock;
use App\\Domains\\Orders\\Enums\\CooperativeOrderStatus;
use App\\Domains\\Orders\\Models\\CooperativeOrder;
use App\\Domains\\Orders\\Services\\OrderCreationService;
use App\\Domains\\Products\\Models\\Product;
use App\\Domains\\Products\\Models\\ProductUnit;
use Database\\Seeders\\PermissionSeeder;
use Database\\Seeders\\RolePermissionSeeder;
use Database\\Seeders\\RoleSeeder;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Tests\\TestCase;

final class CooperativeOrderControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
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
            ->assertSee('INBOX-OWN-001')
            ->assertDontSee('INBOX-OTHER-001');

        $this->actingAs($adminA)
            ->get(route('backoffice.orders.cooperative.show', $suborderB))
            ->assertNotFound();

        $this->assertSame((string) $cooperativeA->getKey(), (string) $suborderA->cooperative_id);
    }

    public function test_cooperative_admin_can_confirm_and_refuse_own_suborders_with_a_required_reason(): void
    {
        $customer = $this->userWithRole('customer');
        $cooperative = Cooperative::factory()->active()->create();
        $admin = $this->userWithRole('cooperative_admin', ['cooperative_id' => $cooperative->getKey()]);
        $confirmOrder = $this->makeOrder($customer, $cooperative, 'INBOX-CONFIRM-001');
        $refuseOrder = $this->makeOrder($customer, $cooperative, 'INBOX-REFUSE-001');
        $toConfirm = $confirmOrder->cooperativeOrders->firstOrFail();
        $toRefuse = $refuseOrder->cooperativeOrders->firstOrFail();

        $this->actingAs($admin)
            ->post(route('backoffice.orders.cooperative.confirm', $toConfirm))
            ->assertRedirect();

        $this->assertSame(CooperativeOrderStatus::CONFIRMED, $toConfirm->fresh()->status);
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
            ->assertSee('INBOX-ASSIGNED-001')
            ->assertDontSee('INBOX-UNASSIGNED-001');

        $this->actingAs($root)
            ->get(route('root.backoffice.orders.cooperative.index'))
            ->assertOk()
            ->assertSee('INBOX-ASSIGNED-001')
            ->assertSee('INBOX-UNASSIGNED-001');
    }

    public function test_root_can_extend_deadline_and_record_a_reasoned_administrative_decision(): void
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

        $this->actingAs($root)
            ->post(route('root.backoffice.orders.cooperative.administrative-decision', $suborder), [
                'accept' => false,
                'reason' => 'Absence de réponse après vérification.',
            ])
            ->assertRedirect();

        $this->assertSame(CooperativeOrderStatus::REFUSED, $suborder->fresh()->status);
        $this->assertSame('Absence de réponse après vérification.', $suborder->fresh()->substitution_reason);
        $this->assertSame((string) $root->getKey(), (string) $suborder->fresh()->substituted_by_admin_id);
    }

    private function makeOrder(User $customer, Cooperative $cooperative, string $key): \\App\\Domains\\Orders\\Models\\Order
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
