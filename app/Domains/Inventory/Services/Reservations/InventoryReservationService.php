<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Services\Reservations;

use App\Core\Foundation\Services\BaseService;
use App\Domains\Audit\Contracts\AuditWriter;
use App\Domains\Inventory\Contracts\InventoryReservationManager;
use App\Domains\Inventory\DTOs\InventoryReservationLineData;
use App\Domains\Inventory\DTOs\InventoryReservationResult;
use App\Domains\Inventory\Enums\InventoryMovementType;
use App\Domains\Inventory\Enums\InventoryReservationStatus;
use App\Domains\Inventory\Exceptions\InsufficientInventoryStock;
use App\Domains\Inventory\Exceptions\InventoryReservationConflict;
use App\Domains\Inventory\Models\InventoryIdempotencyRecord;
use App\Domains\Inventory\Models\InventoryMovement;
use App\Domains\Inventory\Models\InventoryQuarantine;
use App\Domains\Inventory\Models\InventoryQuarantineAuthorization;
use App\Domains\Inventory\Models\InventoryReservationAllocation;
use App\Domains\Inventory\Models\InventoryOutboxMessage;
use App\Domains\Inventory\Models\InventoryReservation;
use App\Domains\Inventory\Models\InventoryReservationItem;
use App\Domains\Inventory\Models\InventoryStock;
use App\Shared\Contracts\ActorContextProvider;
use App\Shared\Contracts\SuperAdminCooperativeAssignmentReader;
use App\Shared\DTOs\ActorContext;
use App\Shared\DTOs\AuditEntryDTO;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use JsonException;

final class InventoryReservationService extends BaseService implements InventoryReservationManager
{
    private const UNITS = ['kg', 'g', 'l', 'ml', 'u'];

    public function __construct(
        private readonly ActorContextProvider $actors,
        private readonly SuperAdminCooperativeAssignmentReader $assignments,
        private readonly AuditWriter $audit,
    ) {}

    public function reserveForSuborder(
        string $cooperativeOrderId,
        string $cooperativeId,
        array $items,
        string $idempotencyKey,
        string $actorId,
    ): InventoryReservationResult {
        if ($items === [] || trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('A reservation requires items and an idempotency key.');
        }

        $actor = $this->actors->forUserId($actorId);
        $this->authorizeReservation($actor, $cooperativeId);

        return DB::transaction(function () use (
            $cooperativeOrderId,
            $cooperativeId,
            $items,
            $idempotencyKey,
            $actor,
        ): InventoryReservationResult {
            $requestHash = $this->reservationRequestHash($cooperativeId, $items);
            $existing = $this->existingResult(
                $cooperativeOrderId,
                'reserve',
                $idempotencyKey,
                $requestHash,
            );

            if ($existing !== null) {
                return $existing;
            }

            $alreadyReserved = InventoryReservation::query()
                ->where('cooperative_order_id', $cooperativeOrderId)
                ->lockForUpdate()
                ->first();

            if ($alreadyReserved !== null) {
                throw new InventoryReservationConflict('A reservation already exists for this cooperative order.');
            }

            $normalizedItems = $this->normalizeLines($items);
            $stocks = $this->lockStocks($cooperativeId, $normalizedItems);
            $reservedAt = now();
            $reservation = InventoryReservation::query()->create([
                'cooperative_order_id' => $cooperativeOrderId,
                'cooperative_id' => $cooperativeId,
                'status' => InventoryReservationStatus::ACTIVE->value,
                'idempotency_key' => $idempotencyKey,
                'actor_id' => $actor->id,
                'actor_type' => $actor->actorType,
                'reserved_at' => $reservedAt,
                'expires_at' => $reservedAt->copy()->addHours(24),
            ]);

            foreach ($normalizedItems as $line) {
                $stock = $stocks->get($line['stock_key']);
                if (! $stock instanceof InventoryStock) {
                    throw new InsufficientInventoryStock(
                        $line['stockable_id'],
                        $this->formatMilli($line['quantity_milli']),
                        '0.000',
                    );
                }

                if ($stock->unit_code !== $line['unit_code']) {
                    throw new InventoryReservationConflict('The requested unit does not match the stock unit.');
                }

                if ($stock->active_count_session_id !== null) {
                    throw new InventoryReservationConflict('This stock is temporarily locked by a physical inventory count.');
                }

                $before = $this->balance($stock);
                $normalAvailable = $this->milli($stock->quantity_on_hand)
                    - $this->milli($stock->quantity_quarantined)
                    - $this->milli($stock->quantity_reserved_normal);
                $quarantineAvailable = $this->milli($stock->quantity_quarantine_authorized)
                    - $this->milli($stock->quantity_reserved_quarantine);

                $normalQuantity = min($line['quantity_milli'], max(0, $normalAvailable));
                $quarantineQuantity = $line['quantity_milli'] - $normalQuantity;

                if ($quarantineQuantity > $quarantineAvailable) {
                    $available = max(0, $normalAvailable) + max(0, $quarantineAvailable);
                    throw new InsufficientInventoryStock(
                        $line['stockable_id'],
                        $this->formatMilli($line['quantity_milli']),
                        $this->formatMilli($available),
                    );
                }

                $quarantineAllocations = [];
                if ($quarantineQuantity > 0) {
                    $authorizations = InventoryQuarantineAuthorization::query()
                        ->where('stock_id', $stock->getKey())
                        ->where('status', 'active')
                        ->orderBy('authorized_at')
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();

                    $remainingToAllocate = $quarantineQuantity;
                    foreach ($authorizations as $authorization) {
                        $authorizationAvailable = $this->milli($authorization->quantity_remaining)
                            - $this->milli($authorization->quantity_reserved);
                        if ($authorizationAvailable <= 0) {
                            continue;
                        }

                        $allocation = min($remainingToAllocate, $authorizationAvailable);
                        $authorization->quantity_reserved = $this->formatMilli(
                            $this->milli($authorization->quantity_reserved) + $allocation,
                        );
                        $authorization->save();

                        $quarantineAllocations[] = [
                            'authorization_id' => (string) $authorization->getKey(),
                            'quantity_milli' => $allocation,
                        ];
                        $remainingToAllocate -= $allocation;

                        if ($remainingToAllocate === 0) {
                            break;
                        }
                    }

                    if ($remainingToAllocate > 0) {
                        throw new InventoryReservationConflict(
                            'The aggregate quarantine availability has no matching active authorization ledger.',
                        );
                    }
                }

                $stock->quantity_reserved = $this->formatMilli(
                    $this->milli($stock->quantity_reserved) + $line['quantity_milli'],
                );
                $stock->quantity_reserved_normal = $this->formatMilli(
                    $this->milli($stock->quantity_reserved_normal) + $normalQuantity,
                );
                $stock->quantity_reserved_quarantine = $this->formatMilli(
                    $this->milli($stock->quantity_reserved_quarantine) + $quarantineQuantity,
                );
                $stock->updated_by = $actor->id;
                $stock->save();

                $reservationItem = InventoryReservationItem::query()->create([
                    'reservation_id' => (string) $reservation->getKey(),
                    'stock_id' => (string) $stock->getKey(),
                    'order_item_id' => $line['order_item_id'],
                    'quantity' => $this->formatMilli($line['quantity_milli']),
                    'quantity_normal' => $this->formatMilli($normalQuantity),
                    'quantity_quarantine' => $this->formatMilli($quarantineQuantity),
                    'unit_code' => $line['unit_code'],
                ]);

                if ($normalQuantity > 0) {
                    InventoryReservationAllocation::query()->create([
                        'reservation_item_id' => (string) $reservationItem->getKey(),
                        'allocation_type' => 'normal',
                        'quarantine_authorization_id' => null,
                        'quantity' => $this->formatMilli($normalQuantity),
                    ]);
                }

                foreach ($quarantineAllocations as $allocation) {
                    InventoryReservationAllocation::query()->create([
                        'reservation_item_id' => (string) $reservationItem->getKey(),
                        'allocation_type' => 'quarantine_authorized',
                        'quarantine_authorization_id' => $allocation['authorization_id'],
                        'quantity' => $this->formatMilli($allocation['quantity_milli']),
                    ]);
                }

                $this->recordMovement(
                    $stock,
                    InventoryMovementType::RESERVATION_CREATED,
                    $before,
                    $actor,
                    $idempotencyKey,
                    reservedDelta: $line['quantity_milli'],
                    reservedNormalDelta: $normalQuantity,
                    reservedQuarantineDelta: $quarantineQuantity,
                    referenceType: 'cooperative_order',
                    referenceId: $cooperativeOrderId,
                );
            }

            $result = $this->result($reservation);
            $this->completeIdempotency(
                $cooperativeOrderId,
                'reserve',
                $idempotencyKey,
                $requestHash,
                $result,
            );

            $this->audit->record(new AuditEntryDTO(
                eventKey: 'inventory.reservation.created',
                action: 'create',
                actorType: $actor->actorType,
                actorId: $actor->id,
                cooperativeId: $cooperativeId,
                subjectType: 'inventory_reservation',
                subjectId: (string) $reservation->getKey(),
                newValues: [
                    'status' => InventoryReservationStatus::ACTIVE->value,
                    'expires_at' => $reservation->expires_at->toIso8601String(),
                    'items_count' => count($normalizedItems),
                ],
            ));

            $this->outbox('inventory.reservation.created', [
                'reservation_id' => (string) $reservation->getKey(),
                'cooperative_order_id' => $cooperativeOrderId,
                'cooperative_id' => $cooperativeId,
                'expires_at' => $reservation->expires_at->toIso8601String(),
            ]);

            return $result;
        }, 3);
    }

    public function consumeForSuborder(
        string $cooperativeOrderId,
        string $idempotencyKey,
        string $actorId,
    ): InventoryReservationResult {
        $reservation = InventoryReservation::query()
            ->where('cooperative_order_id', $cooperativeOrderId)
            ->firstOrFail();
        $actor = $this->actors->forUserId($actorId);
        $this->authorizeManager($actor, (string) $reservation->cooperative_id, 'inventory.reservations.consume');

        return DB::transaction(function () use (
            $cooperativeOrderId,
            $idempotencyKey,
            $actor,
        ): InventoryReservationResult {
            $reservation = InventoryReservation::query()
                ->where('cooperative_order_id', $cooperativeOrderId)
                ->lockForUpdate()
                ->firstOrFail();
            $requestHash = hash('sha256', $cooperativeOrderId);
            $existing = $this->existingResult($cooperativeOrderId, 'consume', $idempotencyKey, $requestHash);

            if ($existing !== null) {
                return $existing;
            }

            if ($reservation->status === InventoryReservationStatus::CONSUMED->value) {
                return $this->result($reservation);
            }

            if ($reservation->status !== InventoryReservationStatus::ACTIVE->value
                || $reservation->expires_at->lessThanOrEqualTo(now())) {
                throw new InventoryReservationConflict('The reservation is no longer active.');
            }

            $items = $reservation->items()->orderBy('stock_id')->get();
            $stocks = InventoryStock::query()
                ->whereIn('id', $items->pluck('stock_id')->unique()->all())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($items as $item) {
                $stock = $stocks->get((string) $item->stock_id);
                if (! $stock instanceof InventoryStock) {
                    throw new InventoryReservationConflict('A reserved stock balance no longer exists.');
                }
                if ($stock->active_count_session_id !== null) {
                    throw new InventoryReservationConflict('This stock is locked by an active physical inventory count.');
                }

                $before = $this->balance($stock);
                $quantity = $this->milli($item->quantity);
                $normal = $this->milli($item->quantity_normal);
                $quarantine = $this->milli($item->quantity_quarantine);

                $quarantineAllocations = InventoryReservationAllocation::query()
                    ->where('reservation_item_id', $item->getKey())
                    ->where('allocation_type', 'quarantine_authorized')
                    ->orderBy('quarantine_authorization_id')
                    ->get();
                $allocatedQuarantine = 0;

                foreach ($quarantineAllocations as $allocation) {
                    $authorization = InventoryQuarantineAuthorization::query()
                        ->whereKey($allocation->quarantine_authorization_id)
                        ->lockForUpdate()
                        ->firstOrFail();
                    $batch = InventoryQuarantine::query()
                        ->whereKey($authorization->quarantine_id)
                        ->lockForUpdate()
                        ->firstOrFail();
                    $allocationQuantity = $this->milli($allocation->quantity);
                    $allocatedQuarantine += $allocationQuantity;

                    $authorization->quantity_reserved = $this->formatMilli(
                        $this->milli($authorization->quantity_reserved) - $allocationQuantity,
                    );
                    $authorization->quantity_remaining = $this->formatMilli(
                        $this->milli($authorization->quantity_remaining) - $allocationQuantity,
                    );
                    if ($authorization->status === 'active' && $this->milli($authorization->quantity_remaining) === 0) {
                        $authorization->status = 'exhausted';
                    }
                    $authorization->save();

                    $batch->quantity_remaining = $this->formatMilli(
                        $this->milli($batch->quantity_remaining) - $allocationQuantity,
                    );
                    $batch->quantity_authorized = $this->formatMilli(
                        $this->milli($batch->quantity_authorized) - $allocationQuantity,
                    );
                    if ($this->milli($batch->quantity_remaining) === 0) {
                        $batch->status = 'exhausted';
                    }
                    $batch->save();
                }

                if ($allocatedQuarantine !== $quarantine) {
                    throw new InventoryReservationConflict('The reservation quarantine allocations do not match the reserved quantity.');
                }

                $stock->quantity_on_hand = $this->formatMilli($this->milli($stock->quantity_on_hand) - $quantity);
                $stock->quantity_reserved = $this->formatMilli($this->milli($stock->quantity_reserved) - $quantity);
                $stock->quantity_reserved_normal = $this->formatMilli($this->milli($stock->quantity_reserved_normal) - $normal);
                $stock->quantity_reserved_quarantine = $this->formatMilli($this->milli($stock->quantity_reserved_quarantine) - $quarantine);

                if ($quarantine > 0) {
                    $stock->quantity_quarantined = $this->formatMilli($this->milli($stock->quantity_quarantined) - $quarantine);
                    $stock->quantity_quarantine_authorized = $this->formatMilli(
                        $this->milli($stock->quantity_quarantine_authorized) - $quarantine,
                    );
                }

                $stock->updated_by = $actor->id;
                $stock->save();

                $this->recordMovement(
                    $stock,
                    InventoryMovementType::SALE,
                    $before,
                    $actor,
                    $idempotencyKey,
                    quantityDelta: -$quantity,
                    reservedDelta: -$quantity,
                    reservedNormalDelta: -$normal,
                    reservedQuarantineDelta: -$quarantine,
                    quarantinedDelta: -$quarantine,
                    quarantineAuthorizedDelta: -$quarantine,
                    referenceType: 'cooperative_order',
                    referenceId: $cooperativeOrderId,
                );
            }

            $reservation->status = InventoryReservationStatus::CONSUMED->value;
            $reservation->consumed_at = now();
            $reservation->save();

            $result = $this->result($reservation);
            $this->completeIdempotency($cooperativeOrderId, 'consume', $idempotencyKey, $requestHash, $result);

            $this->audit->record(new AuditEntryDTO(
                eventKey: 'inventory.reservation.consumed',
                action: 'consume',
                actorType: $actor->actorType,
                actorId: $actor->id,
                cooperativeId: (string) $reservation->cooperative_id,
                subjectType: 'inventory_reservation',
                subjectId: (string) $reservation->getKey(),
                newValues: ['status' => $reservation->status],
            ));

            $this->outbox('inventory.reservation.consumed', [
                'reservation_id' => (string) $reservation->getKey(),
                'cooperative_order_id' => $cooperativeOrderId,
                'cooperative_id' => (string) $reservation->cooperative_id,
            ]);

            return $result;
        }, 3);
    }

    public function releaseForSuborder(
        string $cooperativeOrderId,
        string $idempotencyKey,
        string $reason,
        ?string $actorId,
        bool $expired = false,
    ): ?InventoryReservationResult {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('A reservation release requires a reason.');
        }

        $actor = $actorId === null ? null : $this->actors->forUserId($actorId);
        $reservation = InventoryReservation::query()
            ->where('cooperative_order_id', $cooperativeOrderId)
            ->first();

        if ($reservation === null) {
            return null;
        }

        if ($actor !== null) {
            $this->authorizeRelease($actor, (string) $reservation->cooperative_id);
        }

        return DB::transaction(function () use (
            $cooperativeOrderId,
            $idempotencyKey,
            $reason,
            $actor,
            $expired,
        ): ?InventoryReservationResult {
            $reservation = InventoryReservation::query()
                ->where('cooperative_order_id', $cooperativeOrderId)
                ->lockForUpdate()
                ->first();

            if ($reservation === null) {
                return null;
            }

            $operation = $expired ? 'expire' : 'release';
            $requestHash = hash('sha256', $cooperativeOrderId.'|'.trim($reason));
            $existing = $this->existingResult($cooperativeOrderId, $operation, $idempotencyKey, $requestHash);

            if ($existing !== null) {
                return $existing;
            }

            if ($reservation->status !== InventoryReservationStatus::ACTIVE->value) {
                return $this->result($reservation);
            }

            $items = $reservation->items()->orderBy('stock_id')->get();
            $stocks = InventoryStock::query()
                ->whereIn('id', $items->pluck('stock_id')->unique()->all())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($items as $item) {
                $stock = $stocks->get((string) $item->stock_id);
                if (! $stock instanceof InventoryStock) {
                    throw new InventoryReservationConflict('A reserved stock balance no longer exists.');
                }
                $before = $this->balance($stock);
                $quantity = $this->milli($item->quantity);
                $normal = $this->milli($item->quantity_normal);
                $quarantine = $this->milli($item->quantity_quarantine);
                $quarantineAllocations = InventoryReservationAllocation::query()
                    ->where('reservation_item_id', $item->getKey())
                    ->where('allocation_type', 'quarantine_authorized')
                    ->orderBy('quarantine_authorization_id')
                    ->get();
                $allocatedQuarantine = 0;
                $revokedQuarantine = 0;

                foreach ($quarantineAllocations as $allocation) {
                    $authorization = InventoryQuarantineAuthorization::query()
                        ->whereKey($allocation->quarantine_authorization_id)
                        ->lockForUpdate()
                        ->firstOrFail();
                    $allocationQuantity = $this->milli($allocation->quantity);
                    $allocatedQuarantine += $allocationQuantity;
                    $authorization->quantity_reserved = $this->formatMilli(
                        $this->milli($authorization->quantity_reserved) - $allocationQuantity,
                    );

                    if ($authorization->status === 'revoked') {
                        $authorization->quantity_remaining = $this->formatMilli(
                            $this->milli($authorization->quantity_remaining) - $allocationQuantity,
                        );
                        $batch = InventoryQuarantine::query()
                            ->whereKey($authorization->quarantine_id)
                            ->lockForUpdate()
                            ->firstOrFail();
                        $batch->quantity_authorized = $this->formatMilli(
                            $this->milli($batch->quantity_authorized) - $allocationQuantity,
                        );
                        $batch->save();
                        $revokedQuarantine += $allocationQuantity;
                    }

                    $authorization->save();
                }

                if ($allocatedQuarantine !== $quarantine) {
                    throw new InventoryReservationConflict('The reservation quarantine allocations do not match the reserved quantity.');
                }

                $stock->quantity_reserved = $this->formatMilli($this->milli($stock->quantity_reserved) - $quantity);
                $stock->quantity_reserved_normal = $this->formatMilli($this->milli($stock->quantity_reserved_normal) - $normal);
                $stock->quantity_reserved_quarantine = $this->formatMilli($this->milli($stock->quantity_reserved_quarantine) - $quarantine);
                if ($revokedQuarantine > 0) {
                    $stock->quantity_quarantine_authorized = $this->formatMilli(
                        $this->milli($stock->quantity_quarantine_authorized) - $revokedQuarantine,
                    );
                }
                if ($actor !== null) {
                    $stock->updated_by = $actor->id;
                }
                $stock->save();

                $this->recordMovement(
                    $stock,
                    InventoryMovementType::RESERVATION_RELEASED,
                    $before,
                    $actor,
                    $idempotencyKey,
                    reservedDelta: -$quantity,
                    reservedNormalDelta: -$normal,
                    reservedQuarantineDelta: -$quarantine,
                    quarantineAuthorizedDelta: -$revokedQuarantine,
                    reason: trim($reason),
                    referenceType: 'cooperative_order',
                    referenceId: $cooperativeOrderId,
                );
            }

            $reservation->status = $expired
                ? InventoryReservationStatus::EXPIRED->value
                : InventoryReservationStatus::RELEASED->value;
            $reservation->released_at = now();
            $reservation->release_reason = trim($reason);
            $reservation->save();

            $result = $this->result($reservation);
            $this->completeIdempotency($cooperativeOrderId, $operation, $idempotencyKey, $requestHash, $result);

            $this->audit->record(new AuditEntryDTO(
                eventKey: $expired ? 'inventory.reservation.expired' : 'inventory.reservation.released',
                action: $expired ? 'expire' : 'release',
                actorType: $actor?->actorType ?? 'system',
                actorId: $actor?->id,
                cooperativeId: (string) $reservation->cooperative_id,
                subjectType: 'inventory_reservation',
                subjectId: (string) $reservation->getKey(),
                newValues: [
                    'status' => $reservation->status,
                    'reason' => trim($reason),
                ],
            ));

            $this->outbox(
                $expired ? 'inventory.reservation.expired' : 'inventory.reservation.released',
                [
                    'reservation_id' => (string) $reservation->getKey(),
                    'cooperative_order_id' => $cooperativeOrderId,
                    'cooperative_id' => (string) $reservation->cooperative_id,
                    'reason' => trim($reason),
                ],
            );

            return $result;
        }, 3);
    }

    public function restoreAfterSafetyCancellation(
        string $cooperativeOrderId,
        string $idempotencyKey,
        string $reason,
        string $actorId,
    ): InventoryReservationResult {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('A safety cancellation requires a reason.');
        }

        $reservation = InventoryReservation::query()
            ->where('cooperative_order_id', $cooperativeOrderId)
            ->firstOrFail();
        $actor = $this->actors->forUserId($actorId);
        $this->authorizeManager($actor, (string) $reservation->cooperative_id, 'inventory.reservations.release');

        return DB::transaction(function () use (
            $cooperativeOrderId,
            $idempotencyKey,
            $reason,
            $actor,
        ): InventoryReservationResult {
            $reservation = InventoryReservation::query()
                ->where('cooperative_order_id', $cooperativeOrderId)
                ->lockForUpdate()
                ->firstOrFail();
            $requestHash = hash('sha256', $cooperativeOrderId.'|'.trim($reason));
            $existing = $this->existingResult(
                $cooperativeOrderId,
                'safety_return',
                $idempotencyKey,
                $requestHash,
            );
            if ($existing !== null) {
                return $existing;
            }

            if ($reservation->status === InventoryReservationStatus::SAFETY_RETURNED->value) {
                return $this->result($reservation);
            }

            if ($reservation->status !== InventoryReservationStatus::CONSUMED->value) {
                throw new InventoryReservationConflict('Only a consumed reservation can be restored after a safety cancellation.');
            }

            $items = $reservation->items()->orderBy('stock_id')->get();
            $stocks = InventoryStock::query()
                ->whereIn('id', $items->pluck('stock_id')->unique()->all())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($items as $item) {
                $stock = $stocks->get((string) $item->stock_id);
                if (! $stock instanceof InventoryStock) {
                    throw new InventoryReservationConflict('A consumed stock balance no longer exists.');
                }

                if ($stock->active_count_session_id !== null) {
                    throw new InventoryReservationConflict('A safety return cannot alter stock locked by an active physical inventory count.');
                }

                $before = $this->balance($stock);
                $quantity = $this->milli($item->quantity);
                $quarantine = $this->milli($item->quantity_quarantine);
                $allocations = InventoryReservationAllocation::query()
                    ->where('reservation_item_id', $item->getKey())
                    ->where('allocation_type', 'quarantine_authorized')
                    ->orderBy('quarantine_authorization_id')
                    ->get();
                $allocatedQuarantine = 0;

                foreach ($allocations as $allocation) {
                    $authorization = InventoryQuarantineAuthorization::query()
                        ->whereKey($allocation->quarantine_authorization_id)
                        ->lockForUpdate()
                        ->firstOrFail();
                    $batch = InventoryQuarantine::query()
                        ->whereKey($authorization->quarantine_id)
                        ->lockForUpdate()
                        ->firstOrFail();
                    $allocationQuantity = $this->milli($allocation->quantity);
                    $allocatedQuarantine += $allocationQuantity;

                    $batch->quantity_remaining = $this->formatMilli(
                        $this->milli($batch->quantity_remaining) + $allocationQuantity,
                    );
                    if ($this->milli($batch->quantity_authorized) === 0) {
                        $batch->status = 'non_compliant';
                    } elseif ($this->milli($batch->quantity_authorized) >= $this->milli($batch->quantity_remaining)) {
                        $batch->status = 'authorized_for_sale';
                    } else {
                        $batch->status = 'partially_authorized';
                    }
                    $batch->save();
                }

                if ($allocatedQuarantine !== $quarantine) {
                    throw new InventoryReservationConflict('The consumed quarantine allocations do not match the reservation item.');
                }

                $stock->quantity_on_hand = $this->formatMilli(
                    $this->milli($stock->quantity_on_hand) + $quantity,
                );
                $stock->quantity_quarantined = $this->formatMilli(
                    $this->milli($stock->quantity_quarantined) + $quarantine,
                );
                $stock->updated_by = $actor->id;
                $stock->save();

                $this->recordMovement(
                    $stock,
                    InventoryMovementType::SAFETY_CANCELLATION_RETURN,
                    $before,
                    $actor,
                    $idempotencyKey,
                    quantityDelta: $quantity,
                    quarantinedDelta: $quarantine,
                    reason: trim($reason),
                    referenceType: 'cooperative_order',
                    referenceId: $cooperativeOrderId,
                );
            }

            $reservation->status = InventoryReservationStatus::SAFETY_RETURNED->value;
            $reservation->released_at = now();
            $reservation->release_reason = trim($reason);
            $reservation->save();

            $result = $this->result($reservation);
            $this->completeIdempotency(
                $cooperativeOrderId,
                'safety_return',
                $idempotencyKey,
                $requestHash,
                $result,
            );

            $this->audit->record(new AuditEntryDTO(
                eventKey: 'inventory.reservation.restored_after_safety_cancellation',
                action: 'safety_return',
                actorType: $actor->actorType,
                actorId: $actor->id,
                cooperativeId: (string) $reservation->cooperative_id,
                subjectType: 'inventory_reservation',
                subjectId: (string) $reservation->getKey(),
                newValues: [
                    'status' => $reservation->status,
                    'reason' => trim($reason),
                ],
            ));

            $this->outbox('inventory.reservation.restored_after_safety_cancellation', [
                'reservation_id' => (string) $reservation->getKey(),
                'cooperative_order_id' => $cooperativeOrderId,
                'cooperative_id' => (string) $reservation->cooperative_id,
            ]);

            return $result;
        }, 3);
    }

    private function authorizeReservation(ActorContext $actor, string $cooperativeId): void
    {
        if (! $actor->authorized) {
            throw new AuthorizationException('The actor is not authorized to reserve stock.');
        }

        if ($actor->hasRole('customer')) {
            return;
        }

        $this->authorizeManager($actor, $cooperativeId, 'inventory.reservations.create');
    }

    private function authorizeRelease(ActorContext $actor, string $cooperativeId): void
    {
        if (! $actor->authorized) {
            throw new AuthorizationException('The actor is not authorized to release stock.');
        }

        if ($actor->hasRole('customer')) {
            return;
        }

        $this->authorizeManager($actor, $cooperativeId, 'inventory.reservations.release');
    }

    private function authorizeManager(ActorContext $actor, string $cooperativeId, string $permission): void
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

    /**
     * @param list<InventoryReservationLineData> $items
     * @return list<array<string, int|string>>
     */
    private function normalizeLines(array $items): array
    {
        $normalized = [];

        foreach ($items as $item) {
            if (! $item instanceof InventoryReservationLineData) {
                throw new InvalidArgumentException('Reservation items must be InventoryReservationLineData objects.');
            }

            if (! in_array($item->unitCode, self::UNITS, true)) {
                throw new InvalidArgumentException('Unsupported stock unit.');
            }

            $quantity = $this->milli($item->quantity);
            if ($quantity <= 0 || ($item->unitCode === 'u' && $quantity % 1000 !== 0)) {
                throw new InvalidArgumentException('The requested quantity is invalid for its unit.');
            }

            $stockKey = $item->stockableType->value.':'.$item->stockableId;
            $normalized[] = [
                'order_item_id' => $item->orderItemId,
                'stockable_type' => $item->stockableType->value,
                'stockable_id' => $item->stockableId,
                'stock_key' => $stockKey,
                'quantity_milli' => $quantity,
                'unit_code' => $item->unitCode,
            ];
        }

        usort($normalized, static fn (array $left, array $right): int => strcmp($left['stock_key'], $right['stock_key']));

        return $normalized;
    }

    /**
     * @param list<array<string, int|string>> $items
     * @return Collection<string, InventoryStock>
     */
    private function lockStocks(string $cooperativeId, array $items): Collection
    {
        $query = InventoryStock::query()->where('cooperative_id', $cooperativeId);
        $query->where(function ($builder) use ($items): void {
            foreach ($items as $item) {
                $builder->orWhere(function ($nested) use ($item): void {
                    $nested->where('stockable_type', $item['stockable_type'])
                        ->where('stockable_id', $item['stockable_id']);
                });
            }
        });

        return $query->orderBy('id')->lockForUpdate()->get()->keyBy(
            static fn (InventoryStock $stock): string => $stock->stockable_type.':'.$stock->stockable_id,
        );
    }

    private function recordMovement(
        InventoryStock $stock,
        InventoryMovementType $type,
        array $before,
        ?ActorContext $actor,
        string $idempotencyKey,
        int $quantityDelta = 0,
        int $reservedDelta = 0,
        int $reservedNormalDelta = 0,
        int $reservedQuarantineDelta = 0,
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
            'quantity_delta' => $this->formatSignedMilli($quantityDelta),
            'reserved_delta' => $this->formatSignedMilli($reservedDelta),
            'reserved_normal_delta' => $this->formatSignedMilli($reservedNormalDelta),
            'reserved_quarantine_delta' => $this->formatSignedMilli($reservedQuarantineDelta),
            'quarantined_delta' => $this->formatSignedMilli($quarantinedDelta),
            'quarantine_authorized_delta' => $this->formatSignedMilli($quarantineAuthorizedDelta),
            'balance_before' => $before,
            'balance_after' => $this->balance($stock),
            'unit_code' => $stock->unit_code,
            'reason' => $reason,
            'actor_id' => $actor?->id,
            'actor_type' => $actor?->actorType ?? 'system',
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

    private function existingResult(
        string $subjectId,
        string $operation,
        string $idempotencyKey,
        string $requestHash,
    ): ?InventoryReservationResult {
        $record = InventoryIdempotencyRecord::query()
            ->where('subject_type', 'cooperative_order')
            ->where('subject_id', $subjectId)
            ->where('operation', $operation)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($record === null) {
            return null;
        }

        if (! hash_equals((string) $record->request_hash, $requestHash)) {
            throw new InventoryReservationConflict('The idempotency key was already used with a different request.');
        }

        if (! is_array($record->result)) {
            throw new InventoryReservationConflict('The idempotent operation has no completed result.');
        }

        return new InventoryReservationResult(
            reservationId: (string) $record->result['reservation_id'],
            cooperativeOrderId: (string) $record->result['cooperative_order_id'],
            status: (string) $record->result['status'],
            expiresAt: isset($record->result['expires_at'])
                ? new \DateTimeImmutable((string) $record->result['expires_at'])
                : null,
        );
    }

    private function completeIdempotency(
        string $subjectId,
        string $operation,
        string $idempotencyKey,
        string $requestHash,
        InventoryReservationResult $result,
    ): void {
        InventoryIdempotencyRecord::query()->create([
            'subject_type' => 'cooperative_order',
            'subject_id' => $subjectId,
            'operation' => $operation,
            'idempotency_key' => $idempotencyKey,
            'request_hash' => $requestHash,
            'result' => [
                'reservation_id' => $result->reservationId,
                'cooperative_order_id' => $result->cooperativeOrderId,
                'status' => $result->status,
                'expires_at' => $result->expiresAt?->format(DATE_ATOM),
            ],
            'completed_at' => now(),
        ]);
    }

    /**
     * @param list<InventoryReservationLineData> $items
     *
     * @throws JsonException
     */
    private function reservationRequestHash(string $cooperativeId, array $items): string
    {
        $payload = [];
        foreach ($this->normalizeLines($items) as $item) {
            $payload[] = [
                'order_item_id' => $item['order_item_id'],
                'stockable_type' => $item['stockable_type'],
                'stockable_id' => $item['stockable_id'],
                'quantity_milli' => $item['quantity_milli'],
                'unit_code' => $item['unit_code'],
            ];
        }

        return hash('sha256', json_encode(
            ['cooperative_id' => $cooperativeId, 'items' => $payload],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        ));
    }

    private function result(InventoryReservation $reservation): InventoryReservationResult
    {
        return new InventoryReservationResult(
            reservationId: (string) $reservation->getKey(),
            cooperativeOrderId: (string) $reservation->cooperative_order_id,
            status: (string) $reservation->status,
            expiresAt: $reservation->expires_at === null
                ? null
                : Carbon::instance($reservation->expires_at)->toDateTimeImmutable(),
        );
    }

    private function outbox(string $eventKey, array $payload): void
    {
        InventoryOutboxMessage::query()->create([
            'event_key' => $eventKey,
            'payload' => $payload,
            'available_at' => now(),
        ]);
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

    private function formatSignedMilli(int $value): string
    {
        $sign = $value < 0 ? '-' : '';
        $absolute = abs($value);

        return $sign.intdiv($absolute, 1000).'.'.str_pad((string) ($absolute % 1000), 3, '0', STR_PAD_LEFT);
    }

    private function formatMilli(int $value): string
    {
        if ($value < 0) {
            throw new InventoryReservationConflict('Inventory quantities cannot become negative.');
        }

        return intdiv($value, 1000).'.'.str_pad((string) ($value % 1000), 3, '0', STR_PAD_LEFT);
    }
}
