<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Services\Reservations;

use App\Core\Foundation\Services\BaseService;
use App\Domains\Inventory\Contracts\InventoryOrderAvailabilityReader;
use App\Domains\Inventory\DTOs\InventoryOrderLineAvailability;
use App\Domains\Inventory\DTOs\InventorySuborderAvailability;
use App\Domains\Inventory\Enums\InventoryReservationStatus;
use App\Domains\Inventory\Enums\InventoryStockableType;
use App\Domains\Inventory\Models\InventoryReservation;
use App\Domains\Inventory\Models\InventoryStock;
use App\Domains\Orders\Enums\CooperativeOrderStatus;
use App\Domains\Orders\Models\CooperativeOrder;

final class InventoryOrderAvailabilityService extends BaseService implements InventoryOrderAvailabilityReader
{
    public function forSuborder(string $cooperativeOrderId): InventorySuborderAvailability
    {
        $suborder = CooperativeOrder::query()
            ->with('items')
            ->findOrFail($cooperativeOrderId);
        $reservation = InventoryReservation::query()
            ->with('items')
            ->where('cooperative_order_id', $cooperativeOrderId)
            ->first();

        if ($reservation !== null) {
            $available = $reservation->status === InventoryReservationStatus::CONSUMED->value
                || (
                    $reservation->status === InventoryReservationStatus::ACTIVE->value
                    && ($reservation->expires_at === null || $reservation->expires_at->isFuture())
                );

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

        if ($suborder->status !== CooperativeOrderStatus::AWAITING_COOPERATIVE) {
            return new InventorySuborderAvailability(
                cooperativeOrderId: $cooperativeOrderId,
                reservationStatus: null,
                available: false,
                lines: [],
            );
        }

        $lines = [];
        $demandByStock = [];
        foreach ($suborder->items as $item) {
            $stockableType = $item->product_variant_id === null
                ? InventoryStockableType::PRODUCT->value
                : InventoryStockableType::VARIANT->value;
            $stockableId = (string) ($item->product_variant_id ?? $item->product_id);
            $stockKey = $stockableType.'|'.$stockableId;
            $quantityMilli = $this->toMilli((string) $item->quantity);

            $demandByStock[$stockKey] = ($demandByStock[$stockKey] ?? 0) + $quantityMilli;
            $lines[] = [
                'order_item_id' => (string) $item->getKey(),
                'stockable_type' => $stockableType,
                'stockable_id' => $stockableId,
                'quantity' => (string) $item->quantity,
                'quantity_milli' => $quantityMilli,
                'unit_code' => (string) $item->unit,
                'stock_key' => $stockKey,
            ];
        }

        $stocks = InventoryStock::query()
            ->where('cooperative_id', (string) $suborder->cooperative_id)
            ->where(function ($query) use ($lines): void {
                foreach ($lines as $line) {
                    $query->orWhere(function ($nested) use ($line): void {
                        $nested->where('stockable_type', $line['stockable_type'])
                            ->where('stockable_id', $line['stockable_id']);
                    });
                }
            })
            ->get()
            ->keyBy(static fn (InventoryStock $stock): string => $stock->stockable_type.'|'.$stock->stockable_id);

        $availabilityByStock = [];
        foreach ($demandByStock as $stockKey => $demandMilli) {
            $stock = $stocks->get($stockKey);
            $availableMilli = 0;

            if ($stock instanceof InventoryStock && $stock->active_count_session_id === null) {
                $normal = $this->toMilli((string) $stock->quantity_on_hand)
                    - $this->toMilli((string) $stock->quantity_quarantined)
                    - $this->toMilli((string) $stock->quantity_reserved_normal);
                $quarantine = $this->toMilli((string) $stock->quantity_quarantine_authorized)
                    - $this->toMilli((string) $stock->quantity_reserved_quarantine);
                $availableMilli = max(0, $normal) + max(0, $quarantine);
            }

            $availabilityByStock[$stockKey] = $availableMilli >= $demandMilli;
        }

        $lineResults = array_map(
            static fn (array $line): InventoryOrderLineAvailability => new InventoryOrderLineAvailability(
                orderItemId: $line['order_item_id'],
                quantity: $line['quantity'],
                unitCode: $line['unit_code'],
                available: ($availabilityByStock[$line['stock_key']] ?? false)
                    && $stocks->get($line['stock_key'])?->unit_code === $line['unit_code'],
            ),
            $lines,
        );
        $available = $lineResults !== []
            && collect($lineResults)->every(static fn (InventoryOrderLineAvailability $line): bool => $line->available);

        return new InventorySuborderAvailability(
            cooperativeOrderId: $cooperativeOrderId,
            reservationStatus: null,
            available: $available,
            lines: $lineResults,
        );
    }

    private function toMilli(string $quantity): int
    {
        [$whole, $fraction] = array_pad(explode('.', $quantity, 2), 2, '0');

        return ((int) $whole * 1000) + (int) str_pad($fraction, 3, '0');
    }
}
