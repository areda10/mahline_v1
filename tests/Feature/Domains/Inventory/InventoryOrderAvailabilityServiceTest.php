<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Inventory;

use App\Domains\Inventory\Models\InventoryReservation;
use App\Domains\Inventory\Models\InventoryReservationItem;
use App\Domains\Inventory\Models\InventoryStock;
use App\Domains\Inventory\Services\Reservations\InventoryOrderAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class InventoryOrderAvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_active_reservation_is_unavailable_even_before_expiry_command_runs(): void
    {
        $cooperativeId = (string) Str::ulid();
        $stock = InventoryStock::query()->create([
            'cooperative_id' => $cooperativeId,
            'stockable_type' => 'product',
            'stockable_id' => (string) Str::ulid(),
            'unit_code' => 'u',
            'quantity_on_hand' => '2.000',
        ]);
        $reservation = InventoryReservation::query()->create([
            'cooperative_order_id' => (string) Str::ulid(),
            'cooperative_id' => $cooperativeId,
            'status' => 'active',
            'idempotency_key' => (string) Str::ulid(),
            'reserved_at' => now()->subHours(25),
            'expires_at' => now()->subHour(),
        ]);
        $item = InventoryReservationItem::query()->create([
            'reservation_id' => (string) $reservation->getKey(),
            'stock_id' => (string) $stock->getKey(),
            'order_item_id' => (string) Str::ulid(),
            'quantity' => '1.000',
            'quantity_normal' => '1.000',
            'quantity_quarantine' => '0.000',
            'unit_code' => 'u',
        ]);

        $availability = app(InventoryOrderAvailabilityService::class)
            ->forSuborder((string) $reservation->cooperative_order_id);

        $this->assertFalse($availability->available);
        $this->assertSame('active', $availability->reservationStatus);
        $this->assertCount(1, $availability->lines);
        $this->assertFalse($availability->lines[0]->available);

        $reservation->status = 'consumed';
        $reservation->save();

        $consumedAvailability = app(InventoryOrderAvailabilityService::class)
            ->forSuborder((string) $reservation->cooperative_order_id);

        $this->assertTrue($consumedAvailability->available);
        $this->assertTrue($consumedAvailability->lines[0]->available);
    }
}
