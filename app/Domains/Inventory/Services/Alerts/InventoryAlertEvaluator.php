<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Services\Alerts;

use App\Domains\Inventory\Models\InventoryAlertEpisode;
use App\Domains\Inventory\Models\InventoryOutboxMessage;
use App\Domains\Inventory\Models\InventoryStock;
use App\Domains\Inventory\Models\InventoryStockThreshold;

final class InventoryAlertEvaluator
{
    public function evaluate(InventoryStock $stock): void
    {
        $stock = InventoryStock::query()->whereKey($stock->getKey())->lockForUpdate()->firstOrFail();
        $availableMilli = $this->milli($stock->quantity_on_hand)
            - $this->milli($stock->quantity_reserved_normal)
            - $this->milli($stock->quantity_quarantined)
            + $this->milli($stock->quantity_quarantine_authorized)
            - $this->milli($stock->quantity_reserved_quarantine);
        $availableMilli = max(0, $availableMilli);
        $threshold = InventoryStockThreshold::query()->where('stock_id', $stock->getKey())->first();
        $thresholdMilli = $threshold === null ? null : $this->milli($threshold->low_stock_threshold);

        $this->syncEpisode(
            $stock,
            'out_of_stock',
            $availableMilli === 0,
            $availableMilli,
            $thresholdMilli,
        );

        $this->syncEpisode(
            $stock,
            'low_stock',
            $thresholdMilli !== null && $thresholdMilli > 0 && $availableMilli <= $thresholdMilli,
            $availableMilli,
            $thresholdMilli,
        );
    }

    private function syncEpisode(
        InventoryStock $stock,
        string $type,
        bool $shouldBeActive,
        int $availableMilli,
        ?int $thresholdMilli,
    ): void {
        $active = InventoryAlertEpisode::query()
            ->where('stock_id', $stock->getKey())
            ->where('alert_type', $type)
            ->whereNull('cleared_at')
            ->lockForUpdate()
            ->first();

        if ($shouldBeActive && $active === null) {
            $episode = InventoryAlertEpisode::query()->create([
                'stock_id' => (string) $stock->getKey(),
                'alert_type' => $type,
                'available_quantity_at_trigger' => $this->format($availableMilli),
                'threshold_at_trigger' => $thresholdMilli === null ? null : $this->format($thresholdMilli),
                'triggered_at' => now(),
            ]);

            InventoryOutboxMessage::query()->create([
                'event_key' => 'inventory.stock_alert.triggered',
                'payload' => [
                    'episode_id' => (string) $episode->getKey(),
                    'stock_id' => (string) $stock->getKey(),
                    'cooperative_id' => (string) $stock->cooperative_id,
                    'alert_type' => $type,
                    'available_quantity' => $this->format($availableMilli),
                    'threshold' => $thresholdMilli === null ? null : $this->format($thresholdMilli),
                ],
                'available_at' => now(),
            ]);
        } elseif (! $shouldBeActive && $active !== null) {
            $active->cleared_at = now();
            $active->save();

            InventoryOutboxMessage::query()->create([
                'event_key' => 'inventory.stock_alert.cleared',
                'payload' => [
                    'episode_id' => (string) $active->getKey(),
                    'stock_id' => (string) $stock->getKey(),
                    'cooperative_id' => (string) $stock->cooperative_id,
                    'alert_type' => $type,
                    'available_quantity' => $this->format($availableMilli),
                ],
                'available_at' => now(),
            ]);
        }
    }

    private function milli(string|int|float $value): int
    {
        $formatted = (string) $value;
        [$whole, $fraction] = array_pad(explode('.', $formatted, 2), 2, '0');
        return ((int) $whole * 1000) + (int) str_pad($fraction, 3, '0');
    }

    private function format(int $value): string
    {
        return intdiv($value, 1000).'.'.str_pad((string) ($value % 1000), 3, '0', STR_PAD_LEFT);
    }
}
