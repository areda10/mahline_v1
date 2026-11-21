<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Services\Reservations;

use App\Core\Foundation\Services\BaseService;
use App\Domains\Inventory\Contracts\InventoryCheckoutAvailabilityReader;
use App\Domains\Inventory\DTOs\InventoryCheckoutCooperativeResult;
use App\Domains\Inventory\DTOs\InventoryCheckoutLineData;
use App\Domains\Inventory\DTOs\InventoryCheckoutPreview;
use App\Domains\Inventory\Models\InventoryStock;
use InvalidArgumentException;

final class InventoryCheckoutAvailabilityService extends BaseService implements InventoryCheckoutAvailabilityReader
{
    private const UNITS = ['kg', 'g', 'l', 'ml', 'u'];

    public function preview(array $lines): InventoryCheckoutPreview
    {
        if ($lines === []) {
            throw new InvalidArgumentException('Checkout availability requires at least one line.');
        }

        $normalized = [];
        $seenLineKeys = [];
        foreach ($lines as $line) {
            if (! $line instanceof InventoryCheckoutLineData) {
                throw new InvalidArgumentException('Checkout lines must be InventoryCheckoutLineData objects.');
            }

            if (isset($seenLineKeys[$line->lineKey])) {
                throw new InvalidArgumentException('Checkout line keys must be unique.');
            }
            $seenLineKeys[$line->lineKey] = true;

            if (! in_array($line->unitCode, self::UNITS, true)) {
                throw new InvalidArgumentException('Unsupported stock unit.');
            }

            $quantity = $this->milli($line->quantity);
            if ($quantity <= 0 || ($line->unitCode === 'u' && $quantity % 1000 !== 0)) {
                throw new InvalidArgumentException('The checkout quantity is invalid for its unit.');
            }

            $normalized[] = [
                'line_key' => $line->lineKey,
                'cooperative_id' => $line->cooperativeId,
                'stockable_type' => $line->stockableType->value,
                'stockable_id' => $line->stockableId,
                'quantity_milli' => $quantity,
                'unit_code' => $line->unitCode,
            ];
        }

        usort($normalized, static fn (array $left, array $right): int => [
            $left['cooperative_id'],
            $left['stockable_type'],
            $left['stockable_id'],
            $left['line_key'],
        ] <=> [
            $right['cooperative_id'],
            $right['stockable_type'],
            $right['stockable_id'],
            $right['line_key'],
        ]);

        $stockKeys = [];
        foreach ($normalized as $line) {
            $stockKeys[$line['cooperative_id'].'|'.$line['stockable_type'].'|'.$line['stockable_id']] = $line;
        }

        $query = InventoryStock::query();
        $query->where(function ($builder) use ($stockKeys): void {
            foreach ($stockKeys as $line) {
                $builder->orWhere(function ($nested) use ($line): void {
                    $nested->where('cooperative_id', $line['cooperative_id'])
                        ->where('stockable_type', $line['stockable_type'])
                        ->where('stockable_id', $line['stockable_id']);
                });
            }
        });

        $stocks = $query->get()->keyBy(
            static fn (InventoryStock $stock): string => $stock->cooperative_id.'|'.$stock->stockable_type.'|'.$stock->stockable_id,
        );

        $demandByStock = [];
        $linesByCooperative = [];
        foreach ($normalized as $line) {
            $key = $line['cooperative_id'].'|'.$line['stockable_type'].'|'.$line['stockable_id'];
            $demandByStock[$key] = ($demandByStock[$key] ?? 0) + $line['quantity_milli'];
            $linesByCooperative[$line['cooperative_id']][$key][] = $line;
        }

        $cooperativeResults = [];
        foreach ($linesByCooperative as $cooperativeId => $stockGroups) {
            $unavailableLineKeys = [];

            foreach ($stockGroups as $stockKey => $stockLines) {
                $stock = $stocks->get($stockKey);
                $available = 0;

                $unitCodes = array_values(array_unique(array_column($stockLines, 'unit_code')));
                if ($stock instanceof InventoryStock
                    && $stock->active_count_session_id === null
                    && count($unitCodes) === 1
                    && $stock->unit_code === $unitCodes[0]) {
                    $normalAvailable = $this->milli($stock->quantity_on_hand)
                        - $this->milli($stock->quantity_quarantined)
                        - $this->milli($stock->quantity_reserved_normal);
                    $quarantineAvailable = $this->milli($stock->quantity_quarantine_authorized)
                        - $this->milli($stock->quantity_reserved_quarantine);
                    $available = max(0, $normalAvailable) + max(0, $quarantineAvailable);
                }

                if ($available < $demandByStock[$stockKey]) {
                    foreach ($stockLines as $line) {
                        $unavailableLineKeys[] = $line['line_key'];
                    }
                }
            }

            $cooperativeResults[] = new InventoryCheckoutCooperativeResult(
                cooperativeId: (string) $cooperativeId,
                available: $unavailableLineKeys === [],
                unavailableLineKeys: array_values($unavailableLineKeys),
            );
        }

        $fingerprintPayload = [
            'lines' => $normalized,
            'unavailable_cooperatives' => array_values(array_map(
                static fn (InventoryCheckoutCooperativeResult $result): string => $result->cooperativeId,
                array_filter($cooperativeResults, static fn (InventoryCheckoutCooperativeResult $result): bool => ! $result->available),
            )),
        ];

        return new InventoryCheckoutPreview(
            cooperatives: $cooperativeResults,
            fingerprint: hash('sha256', json_encode($fingerprintPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
        );
    }

    private function milli(string|int|float $value): int
    {
        $formatted = (string) $value;
        if (preg_match('/^\d{1,12}(?:\.\d{1,3})?$/', $formatted) !== 1) {
            throw new InvalidArgumentException('Inventory quantities must be positive decimals with at most three places.');
        }

        [$whole, $fraction] = array_pad(explode('.', $formatted, 2), 2, '0');

        return ((int) $whole * 1000) + (int) str_pad($fraction, 3, '0');
    }
}
