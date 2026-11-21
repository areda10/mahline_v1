<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Services\Stock;

use App\Core\Foundation\Services\BaseService;
use App\Domains\Audit\Contracts\AuditWriter;
use App\Domains\Inventory\Contracts\InventoryStockManager;
use App\Domains\Inventory\Enums\InventoryMovementType;
use App\Domains\Inventory\Enums\InventoryStockableType;
use App\Domains\Inventory\Exceptions\InventoryReservationConflict;
use App\Domains\Inventory\Models\InventoryIdempotencyRecord;
use App\Domains\Inventory\Models\InventoryMovement;
use App\Domains\Inventory\Models\InventoryOutboxMessage;
use App\Domains\Inventory\Models\InventoryStock;
use App\Shared\Contracts\ActorContextProvider;
use App\Shared\Contracts\SuperAdminCooperativeAssignmentReader;
use App\Shared\DTOs\ActorContext;
use App\Shared\DTOs\AuditEntryDTO;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class InventoryStockService extends BaseService implements InventoryStockManager
{
    private const UNITS = ['kg', 'g', 'l', 'ml', 'u'];

    public function __construct(
        private readonly ActorContextProvider $actors,
        private readonly SuperAdminCooperativeAssignmentReader $assignments,
        private readonly AuditWriter $audit,
    ) {}

    public function initialize(
        string $cooperativeId,
        InventoryStockableType $stockableType,
        string $stockableId,
        string $unitCode,
        string $quantity,
        string $reason,
        string $actorId,
        string $idempotencyKey,
    ): string {
        $actor = $this->actors->forUserId($actorId);
        $this->authorize($actor, $cooperativeId, 'inventory.stock.initialize');
        $quantityMilli = $this->quantity($quantity, $unitCode, allowZero: true);
        $this->requireReasonAndKey($reason, $idempotencyKey);

        return DB::transaction(function () use (
            $cooperativeId,
            $stockableType,
            $stockableId,
            $unitCode,
            $quantityMilli,
            $reason,
            $actor,
            $idempotencyKey,
        ): string {
            $stock = InventoryStock::query()
                ->where('cooperative_id', $cooperativeId)
                ->where('stockable_type', $stockableType->value)
                ->where('stockable_id', $stockableId)
                ->lockForUpdate()
                ->first();

            if ($stock !== null) {
                $existing = $this->existingOperation(
                    $stock->getKey(),
                    'initialize',
                    $idempotencyKey,
                    $this->hash([$cooperativeId, $stockableType->value, $stockableId, $unitCode, $quantityMilli, trim($reason)]),
                );
                if ($existing !== null) {
                    return (string) $stock->getKey();
                }

                throw new InventoryReservationConflict('The stock balance has already been initialized.');
            }

            $stock = InventoryStock::query()->create([
                'cooperative_id' => $cooperativeId,
                'stockable_type' => $stockableType->value,
                'stockable_id' => $stockableId,
                'unit_code' => $unitCode,
                'quantity_on_hand' => $this->format($quantityMilli),
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->movement(
                $stock,
                InventoryMovementType::OPENING_BALANCE,
                [
                    'quantity_on_hand' => '0.000',
                    'quantity_reserved' => '0.000',
                    'quantity_reserved_normal' => '0.000',
                    'quantity_reserved_quarantine' => '0.000',
                    'quantity_quarantined' => '0.000',
                    'quantity_quarantine_authorized' => '0.000',
                ],
                $actor,
                $idempotencyKey,
                quantityDelta: $quantityMilli,
                reason: trim($reason),
            );

            $this->completeOperation(
                (string) $stock->getKey(),
                'initialize',
                $idempotencyKey,
                $this->hash([$cooperativeId, $stockableType->value, $stockableId, $unitCode, $quantityMilli, trim($reason)]),
                (string) $stock->getKey(),
            );

            $this->audit->record(new AuditEntryDTO(
                eventKey: 'inventory.stock.initialized',
                action: 'create',
                actorType: $actor->actorType,
                actorId: $actor->id,
                cooperativeId: $cooperativeId,
                subjectType: 'inventory_stock',
                subjectId: (string) $stock->getKey(),
                newValues: [
                    'stockable_type' => $stockableType->value,
                    'stockable_id' => $stockableId,
                    'quantity_on_hand' => $this->format($quantityMilli),
                    'unit_code' => $unitCode,
                    'reason' => trim($reason),
                ],
            ));

            $this->outbox('inventory.stock.changed', [
                'stock_id' => (string) $stock->getKey(),
                'cooperative_id' => $cooperativeId,
            ]);

            return (string) $stock->getKey();
        }, 3);
    }

    public function receive(
        string $cooperativeId,
        InventoryStockableType $stockableType,
        string $stockableId,
        string $quantity,
        string $reason,
        string $actorId,
        string $idempotencyKey,
        string $movementType = 'purchase_receipt',
    ): string {
        $actor = $this->actors->forUserId($actorId);
        $this->authorize($actor, $cooperativeId, 'inventory.stock.receive');
        $quantityMilli = $this->milli($quantity);
        if ($quantityMilli <= 0) {
            throw new InvalidArgumentException('A stock receipt quantity must be positive.');
        }
        $this->requireReasonAndKey($reason, $idempotencyKey);

        $type = InventoryMovementType::tryFrom($movementType);
        if (! in_array($type, [
            InventoryMovementType::PURCHASE_RECEIPT,
            InventoryMovementType::PRODUCTION_ENTRY,
            InventoryMovementType::MANUAL_ADJUSTMENT_IN,
        ], true)) {
            throw new InvalidArgumentException('Unsupported stock receipt movement type.');
        }

        return $this->mutateExistingStock(
            $cooperativeId,
            $stockableType,
            $stockableId,
            $actor,
            $idempotencyKey,
            'receive',
            $this->hash([$cooperativeId, $stockableType->value, $stockableId, $quantityMilli, trim($reason), $type->value]),
            $type,
            $quantityMilli,
            trim($reason),
        );
    }

    public function adjust(
        string $cooperativeId,
        InventoryStockableType $stockableType,
        string $stockableId,
        string $signedQuantity,
        string $reason,
        string $actorId,
        string $idempotencyKey,
    ): string {
        $actor = $this->actors->forUserId($actorId);
        $this->authorize($actor, $cooperativeId, 'inventory.stock.adjust');
        $quantityMilli = $this->signedQuantity($signedQuantity);
        $this->requireReasonAndKey($reason, $idempotencyKey);

        if ($quantityMilli === 0) {
            throw new InvalidArgumentException('A stock adjustment cannot be zero.');
        }

        return $this->mutateExistingStock(
            $cooperativeId,
            $stockableType,
            $stockableId,
            $actor,
            $idempotencyKey,
            'adjust',
            $this->hash([$cooperativeId, $stockableType->value, $stockableId, $quantityMilli, trim($reason)]),
            $quantityMilli > 0 ? InventoryMovementType::MANUAL_ADJUSTMENT_IN : InventoryMovementType::MANUAL_ADJUSTMENT_OUT,
            $quantityMilli,
            trim($reason),
        );
    }

    private function mutateExistingStock(
        string $cooperativeId,
        InventoryStockableType $stockableType,
        string $stockableId,
        ActorContext $actor,
        string $idempotencyKey,
        string $operation,
        string $requestHash,
        InventoryMovementType $movementType,
        int $quantityMilli,
        string $reason,
    ): string {
        return DB::transaction(function () use (
            $cooperativeId,
            $stockableType,
            $stockableId,
            $actor,
            $idempotencyKey,
            $operation,
            $requestHash,
            $movementType,
            $quantityMilli,
            $reason,
        ): string {
            $stock = InventoryStock::query()
                ->where('cooperative_id', $cooperativeId)
                ->where('stockable_type', $stockableType->value)
                ->where('stockable_id', $stockableId)
                ->lockForUpdate()
                ->firstOrFail();

            $existing = $this->existingOperation($stock->getKey(), $operation, $idempotencyKey, $requestHash);
            if ($existing !== null) {
                return (string) $stock->getKey();
            }

            if ($stock->active_count_session_id !== null) {
                throw new InventoryReservationConflict('This stock is locked by an active physical inventory count.');
            }

            if ($stock->unit_code === 'u' && $quantityMilli % 1000 !== 0) {
                throw new InvalidArgumentException('Unit stock adjustments must be integers.');
            }

            $before = $this->balance($stock);
            $currentOnHand = $this->milli($stock->quantity_on_hand);

            if ($operation === 'receive' && $quantityMilli <= 0) {
                throw new InvalidArgumentException('A stock receipt quantity must be positive.');
            }

            if ($quantityMilli < 0) {
                $normalAvailable = $currentOnHand
                    - $this->milli($stock->quantity_quarantined)
                    - $this->milli($stock->quantity_reserved_normal);
                if (abs($quantityMilli) > max(0, $normalAvailable)) {
                    throw new InventoryReservationConflict('The adjustment would reduce available normal stock below zero.');
                }
            }

            $nextOnHand = $currentOnHand + $quantityMilli;
            if ($nextOnHand < 0) {
                throw new InventoryReservationConflict('Physical stock cannot become negative.');
            }

            $stock->quantity_on_hand = $this->format($nextOnHand);
            $stock->updated_by = $actor->id;
            $stock->save();

            $this->movement(
                $stock,
                $movementType,
                $before,
                $actor,
                $idempotencyKey,
                quantityDelta: $quantityMilli,
                reason: $reason,
            );

            $this->completeOperation((string) $stock->getKey(), $operation, $idempotencyKey, $requestHash, (string) $stock->getKey());

            $this->audit->record(new AuditEntryDTO(
                eventKey: 'inventory.stock.adjusted',
                action: $operation,
                actorType: $actor->actorType,
                actorId: $actor->id,
                cooperativeId: $cooperativeId,
                subjectType: 'inventory_stock',
                subjectId: (string) $stock->getKey(),
                oldValues: $before,
                newValues: $this->balance($stock),
                metadata: ['movement_type' => $movementType->value, 'reason' => $reason],
            ));

            $this->outbox('inventory.stock.changed', [
                'stock_id' => (string) $stock->getKey(),
                'cooperative_id' => $cooperativeId,
            ]);

            return (string) $stock->getKey();
        }, 3);
    }

    private function authorize(ActorContext $actor, string $cooperativeId, string $permission): void
    {
        if (! $actor->authorized || ! $actor->hasPermission($permission)) {
            throw new AuthorizationException('The actor does not have the required Inventory permission.');
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

    private function movement(
        InventoryStock $stock,
        InventoryMovementType $type,
        array $before,
        ActorContext $actor,
        string $idempotencyKey,
        int $quantityDelta,
        string $reason,
    ): void {
        InventoryMovement::query()->create([
            'stock_id' => (string) $stock->getKey(),
            'cooperative_id' => (string) $stock->cooperative_id,
            'movement_type' => $type->value,
            'quantity_delta' => $this->formatSigned($quantityDelta),
            'reserved_delta' => '0.000',
            'reserved_normal_delta' => '0.000',
            'reserved_quarantine_delta' => '0.000',
            'quarantined_delta' => '0.000',
            'quarantine_authorized_delta' => '0.000',
            'balance_before' => $before,
            'balance_after' => $this->balance($stock),
            'unit_code' => $stock->unit_code,
            'reason' => $reason,
            'actor_id' => $actor->id,
            'actor_type' => $actor->actorType,
            'idempotency_key' => $idempotencyKey,
            'occurred_at' => now(),
        ]);
    }

    private function balance(InventoryStock $stock): array
    {
        return [
            'quantity_on_hand' => (string) $stock->quantity_on_hand,
            'quantity_reserved' => (string) $stock->quantity_reserved,
            'quantity_reserved_normal' => (string) $stock->quantity_reserved_normal,
            'quantity_reserved_quarantine' => (string) $stock->quantity_reserved_quarantine,
            'quantity_quarantined' => (string) $stock->quantity_quarantined,
            'quantity_quarantine_authorized' => (string) $stock->quantity_quarantine_authorized,
        ];
    }

    private function existingOperation(string $subjectId, string $operation, string $key, string $hash): ?InventoryIdempotencyRecord
    {
        $record = InventoryIdempotencyRecord::query()
            ->where('subject_type', 'inventory_stock')
            ->where('subject_id', $subjectId)
            ->where('operation', $operation)
            ->where('idempotency_key', $key)
            ->first();

        if ($record !== null && ! hash_equals((string) $record->request_hash, $hash)) {
            throw new InventoryReservationConflict('The idempotency key was already used with a different stock operation.');
        }

        return $record;
    }

    private function completeOperation(string $subjectId, string $operation, string $key, string $hash, string $stockId): void
    {
        InventoryIdempotencyRecord::query()->create([
            'subject_type' => 'inventory_stock',
            'subject_id' => $subjectId,
            'operation' => $operation,
            'idempotency_key' => $key,
            'request_hash' => $hash,
            'result' => ['stock_id' => $stockId],
            'completed_at' => now(),
        ]);
    }

    private function outbox(string $eventKey, array $payload): void
    {
        InventoryOutboxMessage::query()->create([
            'event_key' => $eventKey,
            'payload' => $payload,
            'available_at' => now(),
        ]);
    }

    private function requireReasonAndKey(string $reason, string $key): void
    {
        if (trim($reason) === '' || trim($key) === '') {
            throw new InvalidArgumentException('A reason and idempotency key are required.');
        }
    }

    private function quantity(string $value, string $unitCode, bool $allowZero = false): int
    {
        if (! in_array($unitCode, self::UNITS, true)) {
            throw new InvalidArgumentException('Unsupported stock unit.');
        }

        $quantity = $this->milli($value);
        if (($quantity < 0 || (! $allowZero && $quantity === 0)) || ($unitCode === 'u' && $quantity % 1000 !== 0)) {
            throw new InvalidArgumentException('The stock quantity is invalid for its unit.');
        }

        return $quantity;
    }

    private function signedQuantity(string $value): int
    {
        if (preg_match('/^-?\d{1,12}(?:\.\d{1,3})?$/', $value) !== 1) {
            throw new InvalidArgumentException('The adjustment quantity is invalid.');
        }

        $negative = str_starts_with($value, '-');
        $absolute = $negative ? substr($value, 1) : $value;

        return ($negative ? -1 : 1) * $this->milli($absolute);
    }

    private function milli(string|int|float $value): int
    {
        $formatted = (string) $value;
        if (preg_match('/^\d{1,12}(?:\.\d{1,3})?$/', $formatted) !== 1) {
            throw new InvalidArgumentException('Inventory quantities must have at most three decimal places.');
        }

        [$whole, $fraction] = array_pad(explode('.', $formatted, 2), 2, '0');

        return ((int) $whole * 1000) + (int) str_pad($fraction, 3, '0');
    }

    private function format(int $value): string
    {
        if ($value < 0) {
            throw new InventoryReservationConflict('Inventory quantities cannot become negative.');
        }

        return intdiv($value, 1000).'.'.str_pad((string) ($value % 1000), 3, '0', STR_PAD_LEFT);
    }

    private function formatSigned(int $value): string
    {
        $sign = $value < 0 ? '-' : '';
        $absolute = abs($value);

        return $sign.$this->format($absolute);
    }

    private function hash(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
