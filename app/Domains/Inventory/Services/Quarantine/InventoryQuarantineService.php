<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Services\Quarantine;

use App\Core\Foundation\Services\BaseService;
use App\Domains\Audit\Contracts\AuditWriter;
use App\Domains\Inventory\Contracts\InventoryQuarantineManager;
use App\Domains\Inventory\Enums\InventoryInspectionResult;
use App\Domains\Inventory\Enums\InventoryMovementType;
use App\Domains\Inventory\Enums\InventoryStockableType;
use App\Domains\Inventory\Exceptions\InventoryReservationConflict;
use App\Domains\Inventory\Models\InventoryIdempotencyRecord;
use App\Domains\Inventory\Models\InventoryMovement;
use App\Domains\Inventory\Models\InventoryOutboxMessage;
use App\Domains\Inventory\Models\InventoryQuarantine;
use App\Domains\Inventory\Models\InventoryQuarantineAuthorization;
use App\Domains\Inventory\Models\InventoryReservationAllocation;
use App\Domains\Inventory\Models\InventoryStock;
use App\Shared\Contracts\ActorContextProvider;
use App\Shared\Contracts\SuperAdminCooperativeAssignmentReader;
use App\Shared\DTOs\ActorContext;
use App\Shared\DTOs\AuditEntryDTO;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class InventoryQuarantineService extends BaseService implements InventoryQuarantineManager
{
    private const UNITS = ['kg', 'g', 'l', 'ml', 'u'];

    public function __construct(
        private readonly ActorContextProvider $actors,
        private readonly SuperAdminCooperativeAssignmentReader $assignments,
        private readonly AuditWriter $audit,
    ) {}

    public function receiveReturn(
        string $cooperativeId,
        InventoryStockableType $stockableType,
        string $stockableId,
        string $quantity,
        string $sourceType,
        string $sourceId,
        string $reason,
        string $actorId,
        string $idempotencyKey,
    ): string {
        $actor = $this->actors->forUserId($actorId);
        $this->authorize($actor, $cooperativeId, 'inventory.quarantine.manage');
        $this->requireReasonAndKey($reason, $idempotencyKey);
        if (trim($sourceType) === '' || trim($sourceId) === '') {
            throw new InvalidArgumentException('A return quarantine requires a source reference.');
        }

        return DB::transaction(function () use (
            $cooperativeId,
            $stockableType,
            $stockableId,
            $quantity,
            $sourceType,
            $sourceId,
            $reason,
            $actor,
            $idempotencyKey,
        ): string {
            $stock = InventoryStock::query()
                ->where('cooperative_id', $cooperativeId)
                ->where('stockable_type', $stockableType->value)
                ->where('stockable_id', $stockableId)
                ->lockForUpdate()
                ->firstOrFail();

            $quantityMilli = $this->quantity($quantity, $stock->unit_code);
            $requestHash = $this->hash([
                $cooperativeId,
                $stockableType->value,
                $stockableId,
                $quantityMilli,
                trim($sourceType),
                trim($sourceId),
                trim($reason),
            ]);
            $existing = $this->existing(
                'inventory_stock',
                (string) $stock->getKey(),
                'quarantine_receive',
                $idempotencyKey,
                $requestHash,
            );
            if ($existing !== null) {
                return (string) $existing->result['quarantine_id'];
            }

            if ($stock->active_count_session_id !== null) {
                throw new InventoryReservationConflict('This stock is locked by an active physical inventory count.');
            }

            $before = $this->balance($stock);
            $stock->quantity_on_hand = $this->format($this->milli($stock->quantity_on_hand) + $quantityMilli);
            $stock->quantity_quarantined = $this->format($this->milli($stock->quantity_quarantined) + $quantityMilli);
            $stock->updated_by = $actor->id;
            $stock->save();

            $quarantine = InventoryQuarantine::query()->create([
                'stock_id' => (string) $stock->getKey(),
                'cooperative_id' => $cooperativeId,
                'source_type' => trim($sourceType),
                'source_id' => trim($sourceId),
                'quantity_received' => $this->format($quantityMilli),
                'quantity_remaining' => $this->format($quantityMilli),
                'quantity_authorized' => '0.000',
                'status' => 'pending_inspection',
                'inspection_result' => 'pending',
                'reason' => trim($reason),
                'received_by' => $actor->id,
                'received_at' => now(),
            ]);

            $this->movement(
                $stock,
                InventoryMovementType::QUARANTINE_IN,
                $before,
                $actor,
                $idempotencyKey,
                quantityDelta: $quantityMilli,
                quarantinedDelta: $quantityMilli,
                reason: trim($reason),
                referenceType: 'inventory_quarantine',
                referenceId: (string) $quarantine->getKey(),
            );

            $this->complete(
                'inventory_stock',
                (string) $stock->getKey(),
                'quarantine_receive',
                $idempotencyKey,
                $requestHash,
                ['quarantine_id' => (string) $quarantine->getKey()],
            );

            $this->audit->record(new AuditEntryDTO(
                eventKey: 'inventory.quarantine.received',
                action: 'receive',
                actorType: $actor->actorType,
                actorId: $actor->id,
                cooperativeId: $cooperativeId,
                subjectType: 'inventory_quarantine',
                subjectId: (string) $quarantine->getKey(),
                newValues: [
                    'quantity_received' => $this->format($quantityMilli),
                    'source_type' => trim($sourceType),
                    'source_id' => trim($sourceId),
                    'reason' => trim($reason),
                ],
            ));

            $this->outbox('inventory.quarantine.received', [
                'quarantine_id' => (string) $quarantine->getKey(),
                'stock_id' => (string) $stock->getKey(),
                'cooperative_id' => $cooperativeId,
            ]);

            return (string) $quarantine->getKey();
        }, 3);
    }

    public function inspect(
        string $quarantineId,
        InventoryInspectionResult $result,
        string $reason,
        string $actorId,
        string $idempotencyKey,
    ): void {
        $quarantine = InventoryQuarantine::query()->findOrFail($quarantineId);
        $actor = $this->actors->forUserId($actorId);
        $this->authorize($actor, (string) $quarantine->cooperative_id, 'inventory.quarantine.manage');
        $this->requireReasonAndKey($reason, $idempotencyKey);

        DB::transaction(function () use ($quarantineId, $result, $reason, $actor, $idempotencyKey): void {
            $quarantine = InventoryQuarantine::query()
                ->whereKey($quarantineId)
                ->lockForUpdate()
                ->firstOrFail();
            $requestHash = $this->hash([$quarantineId, $result->value, trim($reason)]);
            if ($this->existing('inventory_quarantine', $quarantineId, 'inspect', $idempotencyKey, $requestHash) !== null) {
                return;
            }

            if ($quarantine->inspection_result !== 'pending'
                || $quarantine->status !== 'pending_inspection') {
                throw new InventoryReservationConflict('This quarantine batch has already been inspected.');
            }

            $stock = InventoryStock::query()
                ->whereKey($quarantine->stock_id)
                ->lockForUpdate()
                ->firstOrFail();

            $before = $this->balance($stock);
            $quantity = $this->milli($quarantine->quantity_remaining);

            if ($result === InventoryInspectionResult::COMPLIANT) {
                if ($this->milli($quarantine->quantity_authorized) > 0) {
                    throw new InventoryReservationConflict('A quarantine batch with sale authorizations cannot be released to normal stock.');
                }

                $stock->quantity_quarantined = $this->format(
                    $this->milli($stock->quantity_quarantined) - $quantity,
                );
                $quarantine->quantity_remaining = '0.000';
                $quarantine->status = 'released';
            } else {
                $quarantine->status = 'non_compliant';
            }

            $quarantine->inspection_result = $result->value;
            $quarantine->inspection_reason = trim($reason);
            $quarantine->inspected_by = $actor->id;
            $quarantine->inspected_at = now();
            $stock->updated_by = $actor->id;
            $stock->save();
            $quarantine->save();

            if ($result === InventoryInspectionResult::COMPLIANT) {
                $this->movement(
                    $stock,
                    InventoryMovementType::QUARANTINE_RELEASED,
                    $before,
                    $actor,
                    $idempotencyKey,
                    quarantinedDelta: -$quantity,
                    reason: trim($reason),
                    referenceType: 'inventory_quarantine',
                    referenceId: $quarantineId,
                );
            }

            $this->complete(
                'inventory_quarantine',
                $quarantineId,
                'inspect',
                $idempotencyKey,
                $requestHash,
                ['inspection_result' => $result->value],
            );

            $this->audit->record(new AuditEntryDTO(
                eventKey: 'inventory.quarantine.inspected',
                action: 'inspect',
                actorType: $actor->actorType,
                actorId: $actor->id,
                cooperativeId: (string) $quarantine->cooperative_id,
                subjectType: 'inventory_quarantine',
                subjectId: $quarantineId,
                newValues: [
                    'inspection_result' => $result->value,
                    'inspection_reason' => trim($reason),
                ],
            ));

            $this->outbox('inventory.quarantine.inspected', [
                'quarantine_id' => $quarantineId,
                'cooperative_id' => (string) $quarantine->cooperative_id,
                'inspection_result' => $result->value,
            ]);
        }, 3);
    }

    public function authorizeSale(
        string $quarantineId,
        string $quantity,
        string $reason,
        string $actorId,
        string $idempotencyKey,
    ): string {
        $quarantine = InventoryQuarantine::query()->findOrFail($quarantineId);
        $actor = $this->actors->forUserId($actorId);
        $this->authorize($actor, (string) $quarantine->cooperative_id, 'inventory.quarantine.authorize_sale');
        $this->requireReasonAndKey($reason, $idempotencyKey);

        return DB::transaction(function () use ($quarantineId, $quantity, $reason, $actor, $idempotencyKey): string {
            $quarantine = InventoryQuarantine::query()
                ->whereKey($quarantineId)
                ->lockForUpdate()
                ->firstOrFail();
            $requestHash = $this->hash([$quarantineId, $quantity, trim($reason)]);
            $existing = $this->existing('inventory_quarantine', $quarantineId, 'authorize_sale', $idempotencyKey, $requestHash);
            if ($existing !== null) {
                return (string) $existing->result['authorization_id'];
            }

            if ($quarantine->inspection_result !== InventoryInspectionResult::NON_COMPLIANT->value
                || ! in_array($quarantine->status, ['non_compliant', 'partially_authorized', 'authorized_for_sale'], true)) {
                throw new InventoryReservationConflict('Only inspected non-compliant quarantine batches can receive a conditional sale authorization.');
            }

            $stock = InventoryStock::query()
                ->whereKey($quarantine->stock_id)
                ->lockForUpdate()
                ->firstOrFail();
            if ($stock->active_count_session_id !== null) {
                throw new InventoryReservationConflict('This stock is locked by an active physical inventory count.');
            }

            $quantityMilli = $this->quantity($quantity, $stock->unit_code);
            $notYetAuthorized = $this->milli($quarantine->quantity_remaining)
                - $this->milli($quarantine->quantity_authorized);
            if ($quantityMilli > $notYetAuthorized) {
                throw new InventoryReservationConflict('The authorized quantity cannot exceed the non-authorized quarantine remainder.');
            }

            $before = $this->balance($stock);
            $authorization = InventoryQuarantineAuthorization::query()->create([
                'quarantine_id' => $quarantineId,
                'stock_id' => (string) $stock->getKey(),
                'quantity_authorized' => $this->format($quantityMilli),
                'quantity_remaining' => $this->format($quantityMilli),
                'quantity_reserved' => '0.000',
                'status' => 'active',
                'authorized_by' => $actor->id,
                'reason' => trim($reason),
                'authorized_at' => now(),
            ]);

            $quarantine->quantity_authorized = $this->format(
                $this->milli($quarantine->quantity_authorized) + $quantityMilli,
            );
            $quarantine->status = $this->milli($quarantine->quantity_authorized) >= $this->milli($quarantine->quantity_remaining)
                ? 'authorized_for_sale'
                : 'partially_authorized';
            $quarantine->save();

            $stock->quantity_quarantine_authorized = $this->format(
                $this->milli($stock->quantity_quarantine_authorized) + $quantityMilli,
            );
            $stock->updated_by = $actor->id;
            $stock->save();

            $this->movement(
                $stock,
                InventoryMovementType::QUARANTINE_AUTHORIZED,
                $before,
                $actor,
                $idempotencyKey,
                quarantineAuthorizedDelta: $quantityMilli,
                reason: trim($reason),
                referenceType: 'inventory_quarantine_authorization',
                referenceId: (string) $authorization->getKey(),
            );

            $this->complete(
                'inventory_quarantine',
                $quarantineId,
                'authorize_sale',
                $idempotencyKey,
                $requestHash,
                ['authorization_id' => (string) $authorization->getKey()],
            );

            $this->audit->record(new AuditEntryDTO(
                eventKey: 'inventory.quarantine.sale_authorized',
                action: 'authorize_sale',
                actorType: $actor->actorType,
                actorId: $actor->id,
                cooperativeId: (string) $quarantine->cooperative_id,
                subjectType: 'inventory_quarantine_authorization',
                subjectId: (string) $authorization->getKey(),
                newValues: [
                    'quantity_authorized' => $this->format($quantityMilli),
                    'reason' => trim($reason),
                ],
            ));

            $this->outbox('inventory.quarantine.sale_authorized', [
                'quarantine_id' => $quarantineId,
                'authorization_id' => (string) $authorization->getKey(),
                'cooperative_id' => (string) $quarantine->cooperative_id,
            ]);

            return (string) $authorization->getKey();
        }, 3);
    }

    public function revokeSale(
        string $authorizationId,
        string $reason,
        string $actorId,
        string $idempotencyKey,
    ): void {
        $authorization = InventoryQuarantineAuthorization::query()->findOrFail($authorizationId);
        $actor = $this->actors->forUserId($actorId);
        $this->authorize($actor, (string) $authorization->stock->cooperative_id, 'inventory.quarantine.revoke_sale');
        $this->requireReasonAndKey($reason, $idempotencyKey);

        DB::transaction(function () use ($authorizationId, $reason, $actor, $idempotencyKey): void {
            $authorization = InventoryQuarantineAuthorization::query()
                ->whereKey($authorizationId)
                ->lockForUpdate()
                ->firstOrFail();
            $requestHash = $this->hash([$authorizationId, trim($reason)]);
            if ($this->existing('inventory_quarantine_authorization', $authorizationId, 'revoke_sale', $idempotencyKey, $requestHash) !== null) {
                return;
            }

            if ($authorization->status !== 'active') {
                throw new InventoryReservationConflict('Only an active quarantine sale authorization can be revoked.');
            }

            $stock = InventoryStock::query()
                ->whereKey($authorization->stock_id)
                ->lockForUpdate()
                ->firstOrFail();
            $quarantine = InventoryQuarantine::query()
                ->whereKey($authorization->quarantine_id)
                ->lockForUpdate()
                ->firstOrFail();

            $before = $this->balance($stock);
            $reserved = $this->milli($authorization->quantity_reserved);
            $available = $this->milli($authorization->quantity_remaining) - $reserved;

            $authorization->quantity_remaining = $this->format($reserved);
            $authorization->status = 'revoked';
            $authorization->revoked_by = $actor->id;
            $authorization->revoked_at = now();
            $authorization->revoke_reason = trim($reason);
            $authorization->save();

            $quarantine->quantity_authorized = $this->format(
                $this->milli($quarantine->quantity_authorized) - $available,
            );
            if ($this->milli($quarantine->quantity_authorized) === 0) {
                $quarantine->status = 'non_compliant';
            } else {
                $quarantine->status = 'partially_authorized';
            }
            $quarantine->save();

            $stock->quantity_quarantine_authorized = $this->format(
                $this->milli($stock->quantity_quarantine_authorized) - $available,
            );
            $stock->updated_by = $actor->id;
            $stock->save();

            $this->movement(
                $stock,
                InventoryMovementType::QUARANTINE_AUTHORIZATION_REVOKED,
                $before,
                $actor,
                $idempotencyKey,
                quarantineAuthorizedDelta: -$available,
                reason: trim($reason),
                referenceType: 'inventory_quarantine_authorization',
                referenceId: $authorizationId,
            );

            $affectedOrders = DB::table('inventory_reservation_allocations as allocations')
                ->join('inventory_reservation_items as items', 'items.id', '=', 'allocations.reservation_item_id')
                ->join('inventory_reservations as reservations', 'reservations.id', '=', 'items.reservation_id')
                ->where('allocations.quarantine_authorization_id', $authorizationId)
                ->where('reservations.status', 'consumed')
                ->pluck('reservations.cooperative_order_id')
                ->unique()
                ->values()
                ->all();

            $this->complete(
                'inventory_quarantine_authorization',
                $authorizationId,
                'revoke_sale',
                $idempotencyKey,
                $requestHash,
                ['revoked_quantity' => $this->format($available)],
            );

            $this->audit->record(new AuditEntryDTO(
                eventKey: 'inventory.quarantine.sale_authorization_revoked',
                action: 'revoke_sale',
                actorType: $actor->actorType,
                actorId: $actor->id,
                cooperativeId: (string) $quarantine->cooperative_id,
                subjectType: 'inventory_quarantine_authorization',
                subjectId: $authorizationId,
                newValues: [
                    'revoked_quantity' => $this->format($available),
                    'protected_reserved_quantity' => $this->format($reserved),
                    'reason' => trim($reason),
                ],
            ));

            $this->outbox('inventory.quarantine.authorization.revoked', [
                'quarantine_id' => (string) $quarantine->getKey(),
                'authorization_id' => $authorizationId,
                'cooperative_id' => (string) $quarantine->cooperative_id,
                'affected_cooperative_order_ids' => $affectedOrders,
            ]);
        }, 3);
    }

    private function authorize(ActorContext $actor, string $cooperativeId, string $permission): void
    {
        if (! $actor->authorized || ! $actor->hasPermission($permission)) {
            throw new AuthorizationException('The actor does not have the required Inventory quarantine permission.');
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
        int $quantityDelta = 0,
        int $quarantinedDelta = 0,
        int $quarantineAuthorizedDelta = 0,
        ?string $reason = null,
        ?string $referenceType = null,
        ?string $referenceId = null,
    ): void {
        InventoryMovement::query()->create([
            'stock_id' => (string) $stock->getKey(),
            'cooperative_id' => (string) $stock->cooperative_id,
            'movement_type' => $type->value,
            'quantity_delta' => $this->formatSigned($quantityDelta),
            'reserved_delta' => '0.000',
            'reserved_normal_delta' => '0.000',
            'reserved_quarantine_delta' => '0.000',
            'quarantined_delta' => $this->formatSigned($quarantinedDelta),
            'quarantine_authorized_delta' => $this->formatSigned($quarantineAuthorizedDelta),
            'balance_before' => $before,
            'balance_after' => $this->balance($stock),
            'unit_code' => $stock->unit_code,
            'reason' => $reason,
            'actor_id' => $actor->id,
            'actor_type' => $actor->actorType,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
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

    private function existing(
        string $subjectType,
        string $subjectId,
        string $operation,
        string $key,
        string $requestHash,
    ): ?InventoryIdempotencyRecord {
        $record = InventoryIdempotencyRecord::query()
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->where('operation', $operation)
            ->where('idempotency_key', $key)
            ->first();

        if ($record !== null && ! hash_equals((string) $record->request_hash, $requestHash)) {
            throw new InventoryReservationConflict('The idempotency key was reused with a different quarantine operation.');
        }

        return $record;
    }

    private function complete(
        string $subjectType,
        string $subjectId,
        string $operation,
        string $key,
        string $requestHash,
        array $result,
    ): void {
        InventoryIdempotencyRecord::query()->create([
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'operation' => $operation,
            'idempotency_key' => $key,
            'request_hash' => $requestHash,
            'result' => $result,
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

    private function quantity(string $value, string $unitCode): int
    {
        if (! in_array($unitCode, self::UNITS, true)) {
            throw new InvalidArgumentException('Unsupported stock unit.');
        }

        $quantity = $this->milli($value);
        if ($quantity <= 0 || ($unitCode === 'u' && $quantity % 1000 !== 0)) {
            throw new InvalidArgumentException('The quantity is invalid for its stock unit.');
        }

        return $quantity;
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
