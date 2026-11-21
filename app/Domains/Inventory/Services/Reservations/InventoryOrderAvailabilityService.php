<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Services\Reservations;

use App\Core\Foundation\Services\BaseService;
use App\Domains\Inventory\Contracts\InventoryOrderAvailabilityReader;
use App\Domains\Inventory\DTOs\InventoryOrderLineAvailability;
use App\Domains\Inventory\DTOs\InventorySuborderAvailability;
use App\Domains\Inventory\Enums\InventoryReservationStatus;
use App\Domains\Inventory\Models\InventoryReservation;

final class InventoryOrderAvailabilityService extends BaseService implements InventoryOrderAvailabilityReader
{
    public function forSuborder(string $cooperativeOrderId): InventorySuborderAvailability
    {
        $reservation = InventoryReservation::query()
            ->with('items')
            ->where('cooperative_order_id', $cooperativeOrderId)
            ->first();

        if ($reservation === null) {
            return new InventorySuborderAvailability(
                cooperativeOrderId: $cooperativeOrderId,
                reservationStatus: null,
                available: false,
                lines: [],
            );
        }

        $available = in_array($reservation->status, [
            InventoryReservationStatus::ACTIVE->value,
            InventoryReservationStatus::CONSUMED->value,
        ], true);

        return new InventorySuborderAvailability(
            cooperativeOrderId: $cooperativeOrderId,
            reservationStatus: (string) $reservation->status,
            available: $available,
            lines: $reservation->items
                ->map(static fn ($item): InventoryOrderLineAvailability => new InventoryOrderLineAvailability(
                    orderItemId: (string) $item->order_item_id,
                    quantity: (string) $item->quantity,
                    unitCode: (string) $item->unit_code,
                    available: $available,
                ))
                ->all(),
        );
    }
}
