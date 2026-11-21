<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Services\Alerts;

use App\Core\Foundation\Services\BaseService;
use App\Domains\Audit\Contracts\AuditWriter;
use App\Domains\Inventory\Contracts\InventoryThresholdManager;
use App\Domains\Inventory\Exceptions\InventoryReservationConflict;
use App\Domains\Inventory\Models\InventoryIdempotencyRecord;
use App\Domains\Inventory\Models\InventoryStock;
use App\Domains\Inventory\Models\InventoryStockThreshold;
use App\Domains\Inventory\Models\InventoryThresholdHistory;
use App\Shared\Contracts\ActorContextProvider;
use App\Shared\Contracts\SuperAdminCooperativeAssignmentReader;
use App\Shared\DTOs\ActorContext;
use App\Shared\DTOs\AuditEntryDTO;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class InventoryThresholdService extends BaseService implements InventoryThresholdManager
{
    public function __construct(
        private readonly ActorContextProvider $actors,
        private readonly SuperAdminCooperativeAssignmentReader $assignments,
        private readonly AuditWriter $audit,
        private readonly InventoryAlertEvaluator $alerts,
    ) {}

    public function setLowStockThreshold(
        string $stockId,
        string $threshold,
        string $reason,
        string $actorId,
        string $idempotencyKey,
    ): void {
        $stock = InventoryStock::query()->findOrFail($stockId);
        $actor = $this->actors->forUserId($actorId);
        $this->authorize($actor, (string) $stock->cooperative_id);
        if (trim($reason) === '' || trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('A reason and idempotency key are required.');
        }

        $thresholdMilli = $this->milli($threshold);
        if ($thresholdMilli < 0 || ($stock->unit_code === 'u' && $thresholdMilli % 1000 !== 0)) {
            throw new InvalidArgumentException('The low-stock threshold is invalid for this stock unit.');
        }

        DB::transaction(function () use ($stockId, $thresholdMilli, $reason, $actor, $idempotencyKey): void {
            $stock = InventoryStock::query()->whereKey($stockId)->lockForUpdate()->firstOrFail();
            $hash = hash('sha256', json_encode([$stockId, $thresholdMilli, trim($reason)], JSON_THROW_ON_ERROR));
            $existing = InventoryIdempotencyRecord::query()
                ->where('subject_type', 'inventory_stock')
                ->where('subject_id', $stockId)
                ->where('operation', 'set_low_stock_threshold')
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing !== null) {
                if (! hash_equals((string) $existing->request_hash, $hash)) {
                    throw new InventoryReservationConflict('The threshold idempotency key was reused with different data.');
                }
                return;
            }

            $record = InventoryStockThreshold::query()->where('stock_id', $stockId)->lockForUpdate()->first();
            $old = $record === null ? null : (string) $record->low_stock_threshold;
            $formatted = $this->format($thresholdMilli);

            if ($record === null) {
                $record = InventoryStockThreshold::query()->create([
                    'stock_id' => $stockId,
                    'low_stock_threshold' => $formatted,
                    'updated_by' => $actor->id,
                ]);
            } else {
                $record->low_stock_threshold = $formatted;
                $record->updated_by = $actor->id;
                $record->save();
            }

            InventoryThresholdHistory::query()->create([
                'stock_id' => $stockId,
                'old_threshold' => $old,
                'new_threshold' => $formatted,
                'actor_id' => $actor->id,
                'reason' => trim($reason),
                'occurred_at' => now(),
            ]);

            InventoryIdempotencyRecord::query()->create([
                'subject_type' => 'inventory_stock',
                'subject_id' => $stockId,
                'operation' => 'set_low_stock_threshold',
                'idempotency_key' => $idempotencyKey,
                'request_hash' => $hash,
                'result' => ['threshold' => $formatted],
                'completed_at' => now(),
            ]);

            $this->audit->record(new AuditEntryDTO(
                eventKey: 'inventory.stock_threshold.updated',
                action: 'update',
                actorType: $actor->actorType,
                actorId: $actor->id,
                cooperativeId: (string) $stock->cooperative_id,
                subjectType: 'inventory_stock_threshold',
                subjectId: (string) $record->getKey(),
                oldValues: ['low_stock_threshold' => $old],
                newValues: ['low_stock_threshold' => $formatted, 'reason' => trim($reason)],
            ));

            $this->alerts->evaluate($stock);
        }, 3);
    }

    private function authorize(ActorContext $actor, string $cooperativeId): void
    {
        if (! $actor->authorized || ! $actor->hasPermission('inventory.threshold.manage')) {
            throw new AuthorizationException('The actor cannot manage stock thresholds.');
        }
        if ($actor->hasRole('super_admin_root')) {
            return;
        }
        if ($actor->hasRole('cooperative_admin') && $actor->cooperativeId === $cooperativeId) {
            return;
        }
        if ($actor->hasRole('super_admin') && $this->assignments->isAssigned($actor->id, $cooperativeId)) {
            return;
        }
        throw new AuthorizationException('The actor is outside the cooperative scope.');
    }

    private function milli(string $value): int
    {
        if (preg_match('/^\d{1,12}(?:\.\d{1,3})?$/', $value) !== 1) {
            throw new InvalidArgumentException('The threshold must have at most three decimal places.');
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '0');
        return ((int) $whole * 1000) + (int) str_pad($fraction, 3, '0', STR_PAD_RIGHT);
    }

    private function format(int $value): string
    {
        return intdiv($value, 1000).'.'.str_pad((string) ($value % 1000), 3, '0', STR_PAD_LEFT);
    }
}
