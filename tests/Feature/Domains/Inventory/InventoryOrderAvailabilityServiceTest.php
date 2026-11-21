<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Inventory;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Inventory\Models\InventoryReservation;
use App\Domains\Inventory\Models\InventoryReservationItem;
use App\Domains\Inventory\Models\InventoryStock;
use App\Domains\Inventory\Services\Reservations\InventoryOrderAvailabilityService;
use App\Domains\Orders\Services\OrderCreationService;
use App\Domains\Products\Models\Product;
use App\Domains\Products\Models\ProductUnit;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InventoryOrderAvailabilityServiceTest extends TestCase
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

    public function test_expired_active_reservation_is_unavailable_even_before_expiry_command_runs(): void
    {
        $customerRole = Role::query()->firstOrCreate(
            ['slug' => 'customer'],
            ['name' => 'Customer', 'is_active' => true],
        );
        $customer = User::factory()->create([
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
        ]);
        $customer->roles()->attach($customerRole->getKey());

        $cooperative = Cooperative::factory()->active()->create();
        $product = Product::factory()->forCooperative($cooperative)->create([
            'price' => '20.00',
            'currency' => 'MAD',
            'unit_id' => ProductUnit::query()->where('code', 'u')->value('id'),
        ]);
        $stock = InventoryStock::query()->create([
            'cooperative_id' => (string) $cooperative->getKey(),
            'stockable_type' => 'product',
            'stockable_id' => (string) $product->getKey(),
            'unit_code' => 'u',
            'quantity_on_hand' => '2.000',
        ]);

        $order = app(OrderCreationService::class)->createFromCheckout($customer, [
            'idempotency_key' => 'inventory-availability-expired-01',
            'delivery_address_snapshot' => ['city' => 'Casablanca', 'country' => 'Maroc'],
            'items' => [['product_id' => (string) $product->getKey(), 'quantity' => '1.000']],
        ]);
        $suborder = $order->cooperativeOrders->firstOrFail();

        $reservation = InventoryReservation::query()->create([
            'cooperative_order_id' => (string) $suborder->getKey(),
            'cooperative_id' => (string) $cooperative->getKey(),
            'status' => 'active',
            'idempotency_key' => 'inventory-availability-expired-reservation-01',
            'reserved_at' => now()->subHours(25),
            'expires_at' => now()->subHour(),
        ]);
        $item = InventoryReservationItem::query()->create([
            'reservation_id' => (string) $reservation->getKey(),
            'stock_id' => (string) $stock->getKey(),
            'order_item_id' => (string) $suborder->items->firstOrFail()->getKey(),
            'quantity' => '1.000',
            'quantity_normal' => '1.000',
            'quantity_quarantine' => '0.000',
            'unit_code' => 'u',
        ]);

        $availability = app(InventoryOrderAvailabilityService::class)
            ->forSuborder((string) $suborder->getKey());

        $this->assertFalse($availability->available);
        $this->assertSame('active', $availability->reservationStatus);
        $this->assertCount(1, $availability->lines);
        $this->assertFalse($availability->lines[0]->available);

        $reservation->status = 'consumed';
        $reservation->save();

        $consumedAvailability = app(InventoryOrderAvailabilityService::class)
            ->forSuborder((string) $suborder->getKey());

        $this->assertTrue($consumedAvailability->available);
        $this->assertTrue($consumedAvailability->lines[0]->available);
    }
}
