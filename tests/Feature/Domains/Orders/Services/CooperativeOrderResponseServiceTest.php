<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Orders\Services;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Inventory\Contracts\InventoryReservationManager;
use App\Domains\Inventory\Models\InventoryStock;
use App\Domains\Orders\Enums\CooperativeOrderStatus;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Exceptions\OrderFulfillmentBlocked;
use App\Domains\Orders\Services\CooperativeOrderResponseService;
use App\Domains\Orders\Services\OrderAvailabilityQueryService;
use App\Domains\Orders\Services\OrderCreationService;
use App\Domains\Products\Models\Product;
use App\Domains\Products\Models\ProductUnit;
use App\Shared\Contracts\OrderSafetyHoldManager;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class CooperativeOrderResponseServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);

        ProductUnit::query()->firstOrCreate(
            ['code' => 'u'],
            [
                'label' => 'Unité / pièce (u)',
                'dimension' => 'count',
                'sort_order' => 50,
                'is_active' => true,
            ],
        );
    }

    public function test_cooperative_admin_can_confirm_own_suborder_and_global_status_is_recalculated(): void
    {
        $customer = $this->userWithRole('customer');
        $cooperative = Cooperative::factory()->active()->create();
        $admin = $this->userWithRole('cooperative_admin', [
            'cooperative_id' => $cooperative->getKey(),
        ]);
        $product = Product::factory()->forCooperative($cooperative)->create([
            'price' => '35.00',
            'currency' => 'MAD',
            'unit_id' => ProductUnit::query()->where('code', 'u')->value('id'),
        ]);
        $this->stockFor($cooperative, $product);

        $order = app(OrderCreationService::class)->createFromCheckout($customer, [
            'idempotency_key' => 'test-response-01',
            'delivery_address_snapshot' => ['city' => 'Casablanca', 'country' => 'Maroc'],
            'items' => [['product_id' => (string) $product->getKey(), 'quantity' => '1']],
        ]);

        $suborder = $order->cooperativeOrders->firstOrFail();
        $this->assertTrue($admin->hasRole('cooperative_admin'));
        $this->assertSame((string) $cooperative->getKey(), (string) $admin->cooperative_id);
        $this->assertSame((string) $cooperative->getKey(), (string) $suborder->cooperative_id);
        $updated = app(CooperativeOrderResponseService::class)
            ->confirmByCooperative($suborder, $admin);

        $this->assertSame(CooperativeOrderStatus::CONFIRMED, $updated->status);
        $this->assertSame(OrderStatus::CONFIRMED, $order->fresh()->status);
        $this->assertNotNull($updated->confirmed_at);
    }

    public function test_multi_cooperative_order_becomes_confirmed_only_after_every_suborder_is_confirmed(): void
    {
        $customer = $this->userWithRole('customer');
        $cooperativeA = Cooperative::factory()->active()->create();
        $cooperativeB = Cooperative::factory()->active()->create();
        $adminA = $this->userWithRole('cooperative_admin', [
            'cooperative_id' => $cooperativeA->getKey(),
        ]);
        $adminB = $this->userWithRole('cooperative_admin', [
            'cooperative_id' => $cooperativeB->getKey(),
        ]);
        $productA = Product::factory()->forCooperative($cooperativeA)->create([
            'price' => '20.00',
            'currency' => 'MAD',
            'unit_id' => ProductUnit::query()->where('code', 'u')->value('id'),
        ]);
        $productB = Product::factory()->forCooperative($cooperativeB)->create([
            'price' => '25.00',
            'currency' => 'MAD',
            'unit_id' => ProductUnit::query()->where('code', 'u')->value('id'),
        ]);
        $this->stockFor($cooperativeA, $productA);
        $this->stockFor($cooperativeB, $productB);

        $order = app(OrderCreationService::class)->createFromCheckout($customer, [
            'idempotency_key' => 'test-response-multi-coop-01',
            'delivery_address_snapshot' => ['city' => 'Casablanca', 'country' => 'Maroc'],
            'items' => [
                ['product_id' => (string) $productA->getKey(), 'quantity' => '1'],
                ['product_id' => (string) $productB->getKey(), 'quantity' => '1'],
            ],
        ]);

        $suborders = $order->cooperativeOrders()->orderBy('cooperative_id')->get();
        $this->assertCount(2, $suborders);

        app(CooperativeOrderResponseService::class)->confirmByCooperative(
            $suborders->firstWhere('cooperative_id', (string) $cooperativeA->getKey()),
            $adminA,
        );
        $this->assertSame(OrderStatus::PARTIALLY_CONFIRMED, $order->fresh()->status);

        app(CooperativeOrderResponseService::class)->confirmByCooperative(
            $suborders->firstWhere('cooperative_id', (string) $cooperativeB->getKey()),
            $adminB,
        );
        $this->assertSame(OrderStatus::CONFIRMED, $order->fresh()->status);
    }

    public function test_cooperative_admin_cannot_decide_for_another_cooperative(): void
    {
        $customer = $this->userWithRole('customer');
        $cooperativeA = Cooperative::factory()->active()->create();
        $cooperativeB = Cooperative::factory()->active()->create();
        $adminA = $this->userWithRole('cooperative_admin', [
            'cooperative_id' => $cooperativeA->getKey(),
        ]);
        $productB = Product::factory()->forCooperative($cooperativeB)->create([
            'price' => '20.00',
            'currency' => 'MAD',
            'unit_id' => ProductUnit::query()->where('code', 'u')->value('id'),
        ]);
        $this->stockFor($cooperativeB, $productB);

        $order = app(OrderCreationService::class)->createFromCheckout($customer, [
            'idempotency_key' => 'test-response-02',
            'delivery_address_snapshot' => ['city' => 'Rabat', 'country' => 'Maroc'],
            'items' => [['product_id' => (string) $productB->getKey(), 'quantity' => '1']],
        ]);

        $this->expectException(HttpException::class);

        app(CooperativeOrderResponseService::class)
            ->confirmByCooperative($order->cooperativeOrders->firstOrFail(), $adminA);
    }

    public function test_root_substitution_requires_reason(): void
    {
        $customer = $this->userWithRole('customer');
        $root = $this->userWithRole('super_admin_root');
        $cooperative = Cooperative::factory()->active()->create();
        $product = Product::factory()->forCooperative($cooperative)->create([
            'price' => '20.00',
            'currency' => 'MAD',
            'unit_id' => ProductUnit::query()->where('code', 'u')->value('id'),
        ]);
        $this->stockFor($cooperative, $product);
        $order = app(OrderCreationService::class)->createFromCheckout($customer, [
            'idempotency_key' => 'test-response-03',
            'delivery_address_snapshot' => ['city' => 'Fès', 'country' => 'Maroc'],
            'items' => [['product_id' => (string) $product->getKey(), 'quantity' => '1']],
        ]);

        $suborder = $order->cooperativeOrders->firstOrFail();

        $this->expectException(ValidationException::class);
        app(CooperativeOrderResponseService::class)
            ->decideOnBehalf($suborder, $root, true, '   ');
    }

    public function test_root_can_record_a_reasoned_administrative_decision(): void
    {
        $customer = $this->userWithRole('customer');
        $root = $this->userWithRole('super_admin_root');
        $cooperative = Cooperative::factory()->active()->create();
        $product = Product::factory()->forCooperative($cooperative)->create([
            'price' => '20.00',
            'currency' => 'MAD',
            'unit_id' => ProductUnit::query()->where('code', 'u')->value('id'),
        ]);
        $this->stockFor($cooperative, $product);
        $order = app(OrderCreationService::class)->createFromCheckout($customer, [
            'idempotency_key' => 'test-response-04',
            'delivery_address_snapshot' => ['city' => 'Fès', 'country' => 'Maroc'],
            'items' => [['product_id' => (string) $product->getKey(), 'quantity' => '1']],
        ]);

        $suborder = $order->cooperativeOrders->firstOrFail();
        $updated = app(CooperativeOrderResponseService::class)
            ->decideOnBehalf($suborder, $root, false, 'Absence de réponse coopérative');

        $this->assertSame(CooperativeOrderStatus::REFUSED, $updated->status);
        $this->assertSame((string) $root->getKey(), (string) $updated->substituted_by_admin_id);
        $this->assertSame('Absence de réponse coopérative', $updated->substitution_reason);
        $this->assertSame(OrderStatus::REFUSED, $order->fresh()->status);
    }

    public function test_root_can_extend_cooperative_response_deadline_by_at_most_24_hours(): void
    {
        $customer = $this->userWithRole('customer');
        $root = $this->userWithRole('super_admin_root');
        $cooperative = Cooperative::factory()->active()->create();
        $product = Product::factory()->forCooperative($cooperative)->create([
            'price' => '20.00',
            'currency' => 'MAD',
            'unit_id' => ProductUnit::query()->where('code', 'u')->value('id'),
        ]);
        $this->stockFor($cooperative, $product);
        $order = app(OrderCreationService::class)->createFromCheckout($customer, [
            'idempotency_key' => 'test-response-05',
            'delivery_address_snapshot' => ['city' => 'Fès', 'country' => 'Maroc'],
            'items' => [['product_id' => (string) $product->getKey(), 'quantity' => '1']],
        ]);

        $suborder = $order->cooperativeOrders->firstOrFail();
        $suborder->forceFill(['response_deadline_at' => now()->addHour()])->save();
        $newDeadline = now()->addHours(3);
        $updated = app(CooperativeOrderResponseService::class)
            ->extendResponseDeadline($suborder, $root, $newDeadline, 'Délai supplémentaire accordé');

        $this->assertEquals($newDeadline->timestamp, $updated->response_deadline_at->timestamp);
        $this->assertDatabaseHas('order_status_histories', [
            'subject_type' => 'cooperative_order',
            'subject_id' => $suborder->getKey(),
            'to_status' => CooperativeOrderStatus::AWAITING_COOPERATIVE->value,
        ]);
    }

    public function test_safety_hold_blocks_fulfillment_and_cancellation_restores_stock(): void
    {
        $cooperative = Cooperative::factory()->active()->create();
        $customer = $this->userWithRole('customer');
        $admin = $this->userWithRole('cooperative_admin', [
            'cooperative_id' => $cooperative->getKey(),
        ]);
        $product = Product::factory()->forCooperative($cooperative)->create([
            'price' => '20.00',
            'currency' => 'MAD',
            'unit_id' => ProductUnit::query()->where('code', 'u')->value('id'),
        ]);
        $this->stockFor($cooperative, $product);

        $order = app(OrderCreationService::class)->createFromCheckout($customer, [
            'idempotency_key' => 'test-safety-cancel-01',
            'delivery_address_snapshot' => ['city' => 'Fès', 'country' => 'Maroc'],
            'items' => [[
                'product_id' => (string) $product->getKey(),
                'quantity' => '1',
            ]],
        ]);
        $suborder = $order->cooperativeOrders->firstOrFail();

        app(CooperativeOrderResponseService::class)->confirmByCooperative($suborder, $admin);

        $stock = InventoryStock::query()
            ->where('cooperative_id', $cooperative->getKey())
            ->where('stockable_id', $product->getKey())
            ->firstOrFail();
        $this->assertSame('9.000', $stock->quantity_on_hand);

        $holdId = app(OrderSafetyHoldManager::class)->raiseHold(
            (string) $suborder->getKey(),
            'test_security_report',
            (string) Str::ulid(),
            'Risque de sécurité à examiner.',
        );

        $this->expectException(OrderFulfillmentBlocked::class);
        app(OrderSafetyHoldManager::class)->assertCanShip((string) $suborder->getKey());
    }

    public function test_safety_hold_can_cancel_confirmed_unshipped_suborder_and_restore_stock(): void
    {
        $cooperative = Cooperative::factory()->active()->create();
        $customer = $this->userWithRole('customer');
        $admin = $this->userWithRole('cooperative_admin', [
            'cooperative_id' => $cooperative->getKey(),
        ]);
        $product = Product::factory()->forCooperative($cooperative)->create([
            'price' => '20.00',
            'currency' => 'MAD',
            'unit_id' => ProductUnit::query()->where('code', 'u')->value('id'),
        ]);
        $this->stockFor($cooperative, $product);

        $order = app(OrderCreationService::class)->createFromCheckout($customer, [
            'idempotency_key' => 'test-safety-cancel-02',
            'delivery_address_snapshot' => ['city' => 'Fès', 'country' => 'Maroc'],
            'items' => [[
                'product_id' => (string) $product->getKey(),
                'quantity' => '1',
            ]],
        ]);
        $suborder = $order->cooperativeOrders->firstOrFail();
        app(CooperativeOrderResponseService::class)->confirmByCooperative($suborder, $admin);

        $holdId = app(OrderSafetyHoldManager::class)->raiseHold(
            (string) $suborder->getKey(),
            'test_security_report',
            (string) Str::ulid(),
            'Risque confirmé.',
        );

        app(OrderSafetyHoldManager::class)->resolveHold(
            $holdId,
            'cancel',
            'Annulation de sécurité après examen.',
            (string) $admin->getKey(),
        );

        $suborder->refresh();
        $order->refresh();
        $stock = InventoryStock::query()
            ->where('cooperative_id', $cooperative->getKey())
            ->where('stockable_id', $product->getKey())
            ->firstOrFail();

        $this->assertSame(CooperativeOrderStatus::CANCELLED, $suborder->status);
        $this->assertSame(OrderStatus::CANCELLED, $order->status);
        $this->assertSame('10.000', $stock->quantity_on_hand);
        $this->assertSame('0.000', $stock->quantity_reserved);
    }

    public function test_mono_cooperative_order_exposes_global_and_line_availability_indicators(): void
    {
        $cooperative = Cooperative::factory()->active()->create();
        $customer = $this->userWithRole('customer');
        $product = Product::factory()->forCooperative($cooperative)->create([
            'price' => '20.00',
            'currency' => 'MAD',
            'unit_id' => ProductUnit::query()->where('code', 'u')->value('id'),
        ]);
        $this->stockFor($cooperative, $product);

        $order = app(OrderCreationService::class)->createFromCheckout($customer, [
            'idempotency_key' => 'test-availability-mono-01',
            'delivery_address_snapshot' => ['city' => 'Casablanca', 'country' => 'Maroc'],
            'items' => [[
                'product_id' => (string) $product->getKey(),
                'quantity' => '2',
            ]],
        ]);

        $availability = app(OrderAvailabilityQueryService::class)->forOrder(
            (string) $order->getKey(),
            (string) $customer->getKey(),
        );

        $this->assertFalse($availability['is_multi_cooperative']);
        $this->assertTrue($availability['global_available']);
        $this->assertCount(1, $availability['suborders'][0]['lines']);
        $this->assertTrue($availability['suborders'][0]['lines'][0]['available']);

        $suborder = $order->cooperativeOrders->firstOrFail();
        app(InventoryReservationManager::class)->releaseForSuborder(
            (string) $suborder->getKey(),
            'test-availability-mono-release-01',
            'Libération de test pour vérifier le voyant de disponibilité.',
            (string) $customer->getKey(),
        );

        $released = app(OrderAvailabilityQueryService::class)->forOrder(
            (string) $order->getKey(),
            (string) $customer->getKey(),
        );

        $this->assertFalse($released['global_available']);
        $this->assertFalse($released['suborders'][0]['lines'][0]['available']);
    }

    public function test_multi_cooperative_order_has_suborder_indicators_without_global_or_line_indicators(): void
    {
        $cooperativeA = Cooperative::factory()->active()->create();
        $cooperativeB = Cooperative::factory()->active()->create();
        $customer = $this->userWithRole('customer');
        $adminA = $this->userWithRole('cooperative_admin', [
            'cooperative_id' => $cooperativeA->getKey(),
        ]);
        $productA = Product::factory()->forCooperative($cooperativeA)->create([
            'price' => '20.00',
            'currency' => 'MAD',
            'unit_id' => ProductUnit::query()->where('code', 'u')->value('id'),
        ]);
        $productB = Product::factory()->forCooperative($cooperativeB)->create([
            'price' => '25.00',
            'currency' => 'MAD',
            'unit_id' => ProductUnit::query()->where('code', 'u')->value('id'),
        ]);
        $this->stockFor($cooperativeA, $productA);
        $this->stockFor($cooperativeB, $productB);

        $order = app(OrderCreationService::class)->createFromCheckout($customer, [
            'idempotency_key' => 'test-availability-multi-01',
            'delivery_address_snapshot' => ['city' => 'Casablanca', 'country' => 'Maroc'],
            'items' => [
                ['product_id' => (string) $productA->getKey(), 'quantity' => '1'],
                ['product_id' => (string) $productB->getKey(), 'quantity' => '1'],
            ],
        ]);

        $availability = app(OrderAvailabilityQueryService::class)->forOrder(
            (string) $order->getKey(),
            (string) $customer->getKey(),
        );

        $this->assertTrue($availability['is_multi_cooperative']);
        $this->assertNull($availability['global_available']);
        $this->assertCount(2, $availability['suborders']);
        $this->assertSame([[], []], array_column($availability['suborders'], 'lines'));
        $this->assertTrue($availability['suborders'][0]['available']);
        $this->assertTrue($availability['suborders'][1]['available']);

        $suborderA = $order->cooperativeOrders
            ->firstWhere('cooperative_id', (string) $cooperativeA->getKey());
        app(InventoryReservationManager::class)->releaseForSuborder(
            (string) $suborderA->getKey(),
            'test-availability-multi-release-01',
            'Libération de test de la sous-commande A.',
            (string) $customer->getKey(),
        );

        $afterRelease = app(OrderAvailabilityQueryService::class)->forOrder(
            (string) $order->getKey(),
            (string) $customer->getKey(),
        );
        $byCooperative = collect($afterRelease['suborders'])->keyBy('cooperative_id');
        $this->assertFalse($byCooperative[(string) $cooperativeA->getKey()]['available']);
        $this->assertTrue($byCooperative[(string) $cooperativeB->getKey()]['available']);

        $adminView = app(OrderAvailabilityQueryService::class)->forOrder(
            (string) $order->getKey(),
            (string) $adminA->getKey(),
        );
        $this->assertTrue($adminView['is_multi_cooperative']);
        $this->assertNull($adminView['global_available']);
        $this->assertCount(1, $adminView['suborders']);
        $this->assertSame((string) $cooperativeA->getKey(), $adminView['suborders'][0]['cooperative_id']);
    }

    public function test_multi_cooperative_checkout_with_exclusion_keeps_suborder_indicator_mode(): void
    {
        $cooperativeA = Cooperative::factory()->active()->create();
        $cooperativeB = Cooperative::factory()->active()->create();
        $customer = $this->userWithRole('customer');
        $productA = Product::factory()->forCooperative($cooperativeA)->create([
            'price' => '20.00',
            'currency' => 'MAD',
            'unit_id' => ProductUnit::query()->where('code', 'u')->value('id'),
        ]);
        $productB = Product::factory()->forCooperative($cooperativeB)->create([
            'price' => '25.00',
            'currency' => 'MAD',
            'unit_id' => ProductUnit::query()->where('code', 'u')->value('id'),
        ]);
        $this->stockFor($cooperativeA, $productA);

        $checkout = [
            'delivery_address_snapshot' => ['city' => 'Casablanca', 'country' => 'Maroc'],
            'items' => [
                ['product_id' => (string) $productA->getKey(), 'quantity' => '1'],
                ['product_id' => (string) $productB->getKey(), 'quantity' => '1'],
            ],
        ];
        $preview = app(OrderCreationService::class)->previewFromCheckout($checkout);
        $order = app(OrderCreationService::class)->createFromCheckout(
            $customer,
            $checkout,
            'test-availability-partial-01',
            $preview->fingerprint,
        );

        $this->assertCount(1, $order->cooperativeOrders);
        $this->assertSame([(string) $cooperativeB->getKey()], $order->inventory_excluded_cooperative_ids);

        $availability = app(OrderAvailabilityQueryService::class)->forOrder(
            (string) $order->getKey(),
            (string) $customer->getKey(),
        );

        $this->assertTrue($availability['is_multi_cooperative']);
        $this->assertNull($availability['global_available']);
        $this->assertCount(1, $availability['suborders']);
        $this->assertSame([], $availability['suborders'][0]['lines']);
        $this->assertTrue($availability['suborders'][0]['available']);
    }

    private function stockFor(Cooperative $cooperative, Product $product): void
    {
        InventoryStock::query()->create([
            'cooperative_id' => (string) $cooperative->getKey(),
            'stockable_type' => 'product',
            'stockable_id' => (string) $product->getKey(),
            'unit_code' => 'u',
            'quantity_on_hand' => '10.000',
        ]);
    }

    private function userWithRole(string $roleSlug, array $attributes = []): User
    {
        $role = Role::query()->firstOrCreate(
            ['slug' => $roleSlug],
            ['name' => $roleSlug, 'is_active' => true],
        );

        $user = User::factory()->create(array_merge([
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
        ], $attributes));
        $user->roles()->attach($role->getKey());

        return $user;
    }
}
