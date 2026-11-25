<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Services\Counts;

use App\Core\Foundation\Services\BaseService;
use App\Domains\Audit\Contracts\AuditWriter;
use App\Domains\Inventory\Contracts\InventoryCountManager;
use App\Domains\Products\Contracts\CheckoutCatalog;
use App\Domains\Inventory\Enums\InventoryMovementType;
use App\Domains\Inventory\Exceptions\InventoryReservationConflict;
use App\Domains\Inventory\Models\InventoryCountItem;
use App\Domains\Inventory\Models\InventoryCountItemEvent;
use App\Domains\Inventory\Models\InventoryCountParticipant;
use App\Domains\Inventory\Models\InventoryCountSession;
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

final class InventoryCountService extends BaseService implements InventoryCountManager
{
    private const UNITS = ['kg', 'g', 'l', 'ml', 'u'];

    public function __construct(
        private readonly ActorContextProvider $actors,
        private readonly SuperAdminCooperativeAssignmentReader $assignments,
        private readonly AuditWriter $audit,
        private readonly CheckoutCatalog $products,
    ) {}

    public function createSession(
        string $cooperativeId,
        array $stockIds,
        string $actorId,
        string $idempotencyKey,
    ): string {
        $actor = $this->actors->forUserId($actorId);
        $this->authorizeManager($actor, $cooperativeId, 'inventory.count.create');
        $stockIds = array_values(array_unique(array_map('strval', $stockIds)));
        sort($stockIds);

        if ($stockIds === [] || trim($idempotencyKey) === '' || strlen($idempotencyKey) > 100) {
            throw new InvalidArgumentException('A count session requires stock items and a valid idempotency key.');
        }

        return DB::transaction(function () use ($cooperativeId, $stockIds, $actor, $idempotencyKey): string {
            $requestHash = $this->hash([$cooperativeId, $stockIds]);
            $existing = $this->existing(
                $cooperativeId,
                'create_session',
                $idempotencyKey,
                $requestHash,
            );
            if ($existing !== null) {
                return (string) $existing->result['session_id'];
            }

            $stocks = InventoryStock::query()
                ->where('cooperative_id', $cooperativeId)
                ->whereIn('id', $stockIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($stocks->count() !== count($stockIds)) {
                throw new InventoryReservationConflict('One or more selected stock balances do not belong to this cooperative.');
            }

            foreach ($stocks as $stock) {
                if ($stock->active_count_session_id !== null) {
                    throw new InventoryReservationConflict('A selected stock item is already locked by another active count session.');
                }
            }

            $session = InventoryCountSession::query()->create([
                'cooperative_id' => $cooperativeId,
                'status' => 'in_progress',
                'created_by' => $actor->id,
                'started_at' => now(),
            ]);

            foreach ($stocks as $stock) {
                InventoryCountItem::query()->create([
                    'session_id' => (string) $session->getKey(),
                    'stock_id' => (string) $stock->getKey(),
                    'expected_quantity' => (string) $stock->quantity_on_hand,
                    'status' => 'pending_count',
                ]);

                $stock->active_count_session_id = (string) $session->getKey();
                $stock->updated_by = $actor->id;
                $stock->save();
            }

            $this->addParticipant($session, $actor);
            $this->complete(
                $cooperativeId,
                'create_session',
                $idempotencyKey,
                $requestHash,
                ['session_id' => (string) $session->getKey()],
            );

            $this->audit->record(new AuditEntryDTO(
                eventKey: 'inventory.count_session.created',
                action: 'create',
                actorType: $actor->actorType,
                actorId: $actor->id,
                cooperativeId: $cooperativeId,
                subjectType: 'inventory_count_session',
                subjectId: (string) $session->getKey(),
                newValues: [
                    'stock_count' => $stocks->count(),
                    'status' => 'in_progress',
                ],
            ));

            $this->outbox('inventory.count_session.created', [
                'session_id' => (string) $session->getKey(),
                'cooperative_id' => $cooperativeId,
            ]);

            return (string) $session->getKey();
        }, 3);
    }

    public function recordCount(
        string $countItemId,
        string $countedQuantity,
        ?string $reason,
        string $actorId,
        ?string $expectedSessionId = null,
    ): void {
        $initialItem = InventoryCountItem::query()->with('session')->findOrFail($countItemId);
        $actor = $this->actors->forUserId($actorId);
        $this->authorizeCounter($actor, (string) $initialItem->session->cooperative_id);
        if ($expectedSessionId !== null && (string) $initialItem->session_id !== $expectedSessionId) {
            throw new InventoryReservationConflict('The count item does not belong to the requested session.');
        }

        DB::transaction(function () use ($countItemId, $countedQuantity, $reason, $actor, $initialItem, $expectedSessionId): void {
            $sessionQuery = InventoryCountSession::query()
                ->whereKey($initialItem->session_id);
            if ($expectedSessionId !== null) {
                $sessionQuery->whereKey($expectedSessionId);
            }
            $session = $sessionQuery->lockForUpdate()->firstOrFail();
            $item = InventoryCountItem::query()
                ->whereKey($countItemId)
                ->where('session_id', $session->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $stock = InventoryStock::query()
                ->whereKey($item->stock_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($session->status, ['in_progress', 'partially_validated'], true)
                || $item->status === 'validated'
                || $stock->active_count_session_id !== (string) $session->getKey()) {
                throw new InventoryReservationConflict('This count item is not open for counting.');
            }

            $counted = $this->quantity($countedQuantity, $stock->unit_code);
            $expected = $this->milli($item->expected_quantity);
            $difference = $counted - $expected;
            $oldCount = $item->counted_quantity === null ? null : $this->milli($item->counted_quantity);
            $reason = trim((string) $reason);

            if (($difference !== 0 || ($oldCount !== null && $oldCount !== $counted)) && $reason === '') {
                throw new InvalidArgumentException('A reason is required for a discrepancy or count correction.');
            }

            if ($oldCount !== $counted) {
                InventoryCountItemEvent::query()->create([
                    'count_item_id' => (string) $item->getKey(),
                    'event_type' => $oldCount === null ? 'counted' : 'corrected',
                    'old_quantity' => $oldCount === null ? null : $this->format($oldCount),
                    'new_quantity' => $this->format($counted),
                    'reason' => $reason !== '' ? $reason : null,
                    'actor_id' => $actor->id,
                    'occurred_at' => now(),
                ]);
            }

            $item->counted_quantity = $this->format($counted);
            $item->difference_quantity = $this->formatSigned($difference);
            $item->difference_reason = $reason !== '' ? $reason : null;
            $item->status = 'counted';
            $item->counted_by = $actor->id;
            $item->counted_at = now();
            $item->save();

            $this->addParticipant($session, $actor);

            $this->audit->record(new AuditEntryDTO(
                eventKey: $oldCount === null ? 'inventory.count_item.counted' : 'inventory.count_item.corrected',
                action: $oldCount === null ? 'count' : 'correct',
                actorType: $actor->actorType,
                actorId: $actor->id,
                cooperativeId: (string) $session->cooperative_id,
                subjectType: 'inventory_count_item',
                subjectId: (string) $item->getKey(),
                oldValues: ['counted_quantity' => $oldCount === null ? null : $this->format($oldCount)],
                newValues: [
                    'counted_quantity' => $this->format($counted),
                    'difference_quantity' => $this->formatSigned($difference),
                    'reason' => $reason !== '' ? $reason : null,
                ],
            ));
        }, 3);
    }

    public function validateSession(string $sessionId, string $actorId): void
    {
        $initialSession = InventoryCountSession::query()->findOrFail($sessionId);
        $actor = $this->actors->forUserId($actorId);
        $this->authorizeManager($actor, (string) $initialSession->cooperative_id, 'inventory.count.validate');

        DB::transaction(function () use ($sessionId, $actor): void {
            $session = InventoryCountSession::query()
                ->whereKey($sessionId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($session->status, ['in_progress', 'partially_validated'], true)) {
                throw new InventoryReservationConflict('This inventory count session cannot be validated.');
            }

            $items = InventoryCountItem::query()
                ->where('session_id', $sessionId)
                ->where('status', 'counted')
                ->orderBy('stock_id')
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty()) {
                throw new InventoryReservationConflict('There are no counted items to validate.');
            }

            $stocks = InventoryStock::query()
                ->whereIn('id', $items->pluck('stock_id')->unique()->all())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($items as $item) {
                $stock = $stocks->get((string) $item->stock_id);
                if (! $stock instanceof InventoryStock
                    || $stock->active_count_session_id !== $sessionId) {
                    throw new InventoryReservationConflict('A count item lost its exclusive stock lock.');
                }

                $counted = $this->milli((string) $item->counted_quantity);
                $expected = $this->milli((string) $item->expected_quantity);
                $difference = $counted - $expected;
                if ($difference !== 0) {
                    $stockable = $this->products->resolveForInventory(
                        (string) $stock->stockable_type,
                        (string) $stock->stockable_id,
                    );
                    if ($stockable === null || ! $stockable->isOperational) {
                        throw new InventoryReservationConflict('A count discrepancy cannot adjust stock after its product or variant became inactive or deleted.');
                    }
                }

                if ($difference !== 0 && trim((string) $item->difference_reason) === '') {
                    throw new InvalidArgumentException('A reason is required for every physical count discrepancy.');
                }

                $minimumPhysical = $this->milli($stock->quantity_quarantined)
                    + $this->milli($stock->quantity_reserved_normal);
                if ($counted < $minimumPhysical) {
                    throw new InventoryReservationConflict('The counted quantity is lower than stock already reserved or held in quarantine.');
                }

                $before = $this->balance($stock);
                $stock->quantity_on_hand = $this->format($counted);
                $stock->active_count_session_id = null;
                $stock->updated_by = $actor->id;
                $stock->save();

                if ($difference !== 0) {
                    InventoryMovement::query()->create([
                        'stock_id' => (string) $stock->getKey(),
                        'cooperative_id' => (string) $stock->cooperative_id,
                        'movement_type' => InventoryMovementType::INVENTORY_COUNT_ADJUSTMENT->value,
                        'quantity_delta' => $this->formatSigned($difference),
                        'reserved_delta' => '0.000',
                        'reserved_normal_delta' => '0.000',
                        'reserved_quarantine_delta' => '0.000',
                        'quarantined_delta' => '0.000',
                        'quarantine_authorized_delta' => '0.000',
                        'balance_before' => $before,
                        'balance_after' => $this->balance($stock),
                        'unit_code' => $stock->unit_code,
                        'reason' => $item->difference_reason,
                        'actor_id' => $actor->id,
                        'actor_type' => $actor->actorType,
                        'reference_type' => 'inventory_count_session',
                        'reference_id' => $sessionId,
                        'idempotency_key' => hash('sha256', $sessionId.'|'.$item->getKey().'|validate'),
                        'occurred_at' => now(),
                    ]);
                }

                $item->status = 'validated';
                $item->validated_by = $actor->id;
                $item->validated_at = now();
                $item->save();

                InventoryCountItemEvent::query()->create([
                    'count_item_id' => (string) $item->getKey(),
                    'event_type' => 'validated',
                    'old_quantity' => $this->format($expected),
                    'new_quantity' => $this->format($counted),
                    'reason' => $item->difference_reason,
                    'actor_id' => $actor->id,
                    'occurred_at' => now(),
                ]);
            }

            $remaining = InventoryCountItem::query()
                ->where('session_id', $sessionId)
                ->where('status', '!=', 'validated')
                ->exists();

            $session->status = $remaining ? 'partially_validated' : 'completed';
            $session->validated_by = $actor->id;
            if (! $remaining) {
                $session->completed_at = now();
            }
            $session->save();

            $this->addParticipant($session, $actor);

            $this->audit->record(new AuditEntryDTO(
                eventKey: $remaining ? 'inventory.count_session.partially_validated' : 'inventory.count_session.completed',
                action: 'validate',
                actorType: $actor->actorType,
                actorId: $actor->id,
                cooperativeId: (string) $session->cooperative_id,
                subjectType: 'inventory_count_session',
                subjectId: $sessionId,
                newValues: [
                    'status' => $session->status,
                    'validated_item_count' => $items->count(),
                    'remaining_item_count' => InventoryCountItem::query()->where('session_id', $sessionId)->where('status', '!=', 'validated')->count(),
                ],
            ));

            $this->outbox('inventory.count_session.validated', [
                'session_id' => $sessionId,
                'cooperative_id' => (string) $session->cooperative_id,
                'status' => $session->status,
            ]);
        }, 3);
    }

    public function cancelSession(
        string $sessionId,
        string $reason,
        string $actorId,
    ): void {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('A cancellation reason is required.');
        }

        $initialSession = InventoryCountSession::query()->findOrFail($sessionId);
        $actor = $this->actors->forUserId($actorId);
        $this->authorizeManager($actor, (string) $initialSession->cooperative_id, 'inventory.count.cancel');

        DB::transaction(function () use ($sessionId, $reason, $actor): void {
            $session = InventoryCountSession::query()
                ->whereKey($sessionId)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($session->status, ['completed', 'cancelled'], true)) {
                throw new InventoryReservationConflict('A completed or cancelled count session cannot be cancelled again.');
            }

            $stocks = InventoryStock::query()
                ->where('active_count_session_id', $sessionId)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($stocks as $stock) {
                $stock->active_count_session_id = null;
                $stock->updated_by = $actor->id;
                $stock->save();
            }

            $session->status = 'cancelled';
            $session->cancelled_by = $actor->id;
            $session->cancelled_at = now();
            $session->cancellation_reason = trim($reason);
            $session->save();

            $this->addParticipant($session, $actor);

            $this->audit->record(new AuditEntryDTO(
                eventKey: 'inventory.count_session.cancelled',
                action: 'cancel',
                actorType: $actor->actorType,
                actorId: $actor->id,
                cooperativeId: (string) $session->cooperative_id,
                subjectType: 'inventory_count_session',
                subjectId: $sessionId,
                newValues: [
                    'status' => 'cancelled',
                    'reason' => trim($reason),
                    'released_stock_lock_count' => $stocks->count(),
                ],
            ));

            $this->outbox('inventory.count_session.cancelled', [
                'session_id' => $sessionId,
                'cooperative_id' => (string) $session->cooperative_id,
            ]);
        }, 3);
    }

    private function authorizeCounter(ActorContext $actor, string $cooperativeId): void
    {
        if (! $actor->authorized || ! $actor->hasPermission('inventory.count.perform')) {
            throw new AuthorizationException('The actor cannot perform physical stock counts.');
        }

        if ($actor->hasRole('user') && $actor->cooperativeId === $cooperativeId) {
            return;
        }

        $this->authorizeManager($actor, $cooperativeId, 'inventory.count.perform');
    }

    private function authorizeManager(ActorContext $actor, string $cooperativeId, string $permission): void
    {
        if (! $actor->authorized || ! $actor->hasPermission($permission)) {
            throw new AuthorizationException('The actor does not have the required Inventory count permission.');
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

    private function addParticipant(InventoryCountSession $session, ActorContext $actor): void
    {
        InventoryCountParticipant::query()->firstOrCreate(
            [
                'session_id' => (string) $session->getKey(),
                'user_id' => $actor->id,
            ],
            [
                'participant_role' => $this->actorRole($actor),
                'joined_at' => now(),
            ],
        );
    }

    private function actorRole(ActorContext $actor): string
    {
        foreach (['super_admin_root', 'super_admin', 'cooperative_admin', 'user'] as $role) {
            if ($actor->hasRole($role)) {
                return $role;
            }
        }

        return 'system';
    }

    private function existing(
        string $cooperativeId,
        string $operation,
        string $key,
        string $requestHash,
    ): ?InventoryIdempotencyRecord {
        $record = InventoryIdempotencyRecord::query()
            ->where('subject_type', 'inventory_count')
            ->where('subject_id', $cooperativeId)
            ->where('operation', $operation)
            ->where('idempotency_key', $key)
            ->first();

        if ($record !== null && ! hash_equals((string) $record->request_hash, $requestHash)) {
            throw new InventoryReservationConflict('The count idempotency key was reused with different stock items.');
        }

        return $record;
    }

    private function complete(
        string $cooperativeId,
        string $operation,
        string $key,
        string $requestHash,
        array $result,
    ): void {
        InventoryIdempotencyRecord::query()->create([
            'subject_type' => 'inventory_count',
            'subject_id' => $cooperativeId,
            'operation' => $operation,
            'idempotency_key' => $key,
            'request_hash' => $requestHash,
            'result' => $result,
            'completed_at' => now(),
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

    private function outbox(string $eventKey, array $payload): void
    {
        InventoryOutboxMessage::query()->create([
            'event_key' => $eventKey,
            'payload' => $payload,
            'available_at' => now(),
        ]);
    }

    private function quantity(string $value, string $unitCode): int
    {
        if (! in_array($unitCode, self::UNITS, true)) {
            throw new InvalidArgumentException('Unsupported stock unit.');
        }

        $quantity = $this->milli($value);
        if ($quantity < 0 || ($unitCode === 'u' && $quantity % 1000 !== 0)) {
            throw new InvalidArgumentException('The counted quantity is invalid for its stock unit.');
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
