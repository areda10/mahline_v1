<?php

declare(strict_types=1);

namespace App\Domains\Orders\Services;

use App\Core\Foundation\Services\BaseService;
use App\Domains\Audit\Contracts\AuditWriter;
use App\Domains\Inventory\Contracts\InventoryReservationManager;
use App\Shared\Contracts\OrderSafetyHoldManager;
use App\Domains\Orders\Enums\CooperativeOrderStatus;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Exceptions\OrderFulfillmentBlocked;
use App\Domains\Orders\Models\CooperativeOrder;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderSafetyHold;
use App\Domains\Orders\Models\OrderStatusHistory;
use App\Shared\Contracts\ActorContextProvider;
use App\Shared\Contracts\SuperAdminCooperativeAssignmentReader;
use App\Shared\DTOs\ActorContext;
use App\Shared\DTOs\AuditEntryDTO;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class OrderSafetyHoldService extends BaseService implements OrderSafetyHoldManager
{
    public function __construct(
        private readonly AuditWriter $audit,
        private readonly InventoryReservationManager $inventoryReservations,
        private readonly ActorContextProvider $actors,
        private readonly SuperAdminCooperativeAssignmentReader $assignments,
    ) {}

    public function assertSecurityReportCanReopen(?string $cooperativeOrderId): void
    {
        if ($cooperativeOrderId === null) {
            return;
        }

        $order = CooperativeOrder::query()
            ->whereKey($cooperativeOrderId)
            ->lockForUpdate()
            ->firstOrFail();
        if (in_array($order->status, [
            CooperativeOrderStatus::SHIPPED,
            CooperativeOrderStatus::DELIVERED,
            CooperativeOrderStatus::RETURNED,
        ], true)) {
            throw new OrderFulfillmentBlocked('V1 does not support reopening a security report linked to an already shipped suborder.');
        }
    }

    public function assertCooperativeOrderScope(string $cooperativeOrderId, string $cooperativeId): void
    {
        $cooperativeOrder = CooperativeOrder::query()->findOrFail($cooperativeOrderId);
        if ((string) $cooperativeOrder->cooperative_id !== $cooperativeId) {
            throw new AuthorizationException('The cooperative order is outside the requested cooperative scope.');
        }
    }

    public function raiseHold(
        string $cooperativeOrderId,
        string $sourceType,
        string $sourceId,
        string $reason,
        ?string $actorId = null,
        ?string $actorType = 'system',
        ?string $expectedCooperativeId = null,
    ): string {
        return DB::transaction(function () use (
            $cooperativeOrderId,
            $sourceType,
            $sourceId,
            $reason,
            $actorId,
            $actorType,
            $expectedCooperativeId,
        ): string {
            $cooperativeOrder = CooperativeOrder::query()
                ->whereKey($cooperativeOrderId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($expectedCooperativeId !== null
                && (string) $cooperativeOrder->cooperative_id !== $expectedCooperativeId) {
                throw new AuthorizationException('The safety hold order is outside the expected cooperative scope.');
            }

            if (! in_array($cooperativeOrder->status, [
                CooperativeOrderStatus::CONFIRMED,
                CooperativeOrderStatus::PREPARING,
                CooperativeOrderStatus::READY_FOR_SHIPMENT,
            ], true)) {
                return '';
            }

            $hold = OrderSafetyHold::query()->firstOrCreate(
                [
                    'cooperative_order_id' => $cooperativeOrderId,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                ],
                [
                    'cooperative_id' => (string) $cooperativeOrder->cooperative_id,
                    'reason' => trim($reason),
                    'status' => 'active',
                    'raised_by_id' => $actorId,
                    'raised_by_type' => $actorType,
                    'raised_at' => now(),
                ],
            );

            $wasRecentlyCreated = $hold->wasRecentlyCreated;
            $previousStatus = $hold->status;
            if (! $wasRecentlyCreated && $previousStatus === 'active') {
                return (string) $hold->getKey();
            }

            if ($hold->status !== 'active') {
                $hold->status = 'active';
                $hold->reason = trim($reason);
                $hold->raised_by_id = $actorId;
                $hold->raised_by_type = $actorType;
                $hold->raised_at = now();
                $hold->resolved_by_id = null;
                $hold->resolved_by_type = null;
                $hold->resolution = null;
                $hold->resolution_reason = null;
                $hold->resolved_at = null;
                $hold->save();
            }

            $this->audit->record(new AuditEntryDTO(
                eventKey: $wasRecentlyCreated ? 'orders.safety_hold.raised' : 'orders.safety_hold.reopened',
                action: $wasRecentlyCreated ? 'create' : 'update',
                actorType: $actorType ?? 'system',
                actorId: $actorId,
                cooperativeId: (string) $cooperativeOrder->cooperative_id,
                subjectType: 'order_safety_hold',
                subjectId: (string) $hold->getKey(),
                oldValues: $wasRecentlyCreated ? [] : ['status' => $previousStatus],
                newValues: ['status' => $hold->status, 'source_type' => $sourceType, 'source_id' => $sourceId, 'reason' => trim($reason)],
            ));

            return (string) $hold->getKey();
        }, 3);
    }

    public function resolveHold(
        string $holdId,
        string $decision,
        string $reason,
        string $actorId,
    ): void {
        if (! in_array($decision, ['resume', 'cancel'], true) || trim($reason) === '') {
            throw new InvalidArgumentException('A valid safety decision and reason are required.');
        }

        $initialHold = OrderSafetyHold::query()->findOrFail($holdId);
        $actor = $this->actors->forUserId($actorId);
        $this->authorizeResolution($actor, (string) $initialHold->cooperative_id);

        DB::transaction(function () use ($holdId, $decision, $reason, $actor, $initialHold): void {
            $cooperativeOrder = CooperativeOrder::query()
                ->whereKey($initialHold->cooperative_order_id)
                ->lockForUpdate()
                ->firstOrFail();

            $hold = OrderSafetyHold::query()
                ->whereKey($holdId)
                ->lockForUpdate()
                ->firstOrFail();

            if ((string) $hold->cooperative_order_id !== (string) $cooperativeOrder->getKey()) {
                throw new OrderFulfillmentBlocked('The safety hold order reference changed unexpectedly.');
            }

            if ($hold->status !== 'active') {
                throw new OrderFulfillmentBlocked('This safety hold has already been resolved.');
            }

            if (in_array($cooperativeOrder->status, [
                CooperativeOrderStatus::SHIPPED,
                CooperativeOrderStatus::DELIVERED,
                CooperativeOrderStatus::RETURNED,
            ], true)) {
                throw new OrderFulfillmentBlocked('V1 does not support safety hold resolution after shipment.');
            }

            if ($decision === 'cancel') {
                if (! in_array($cooperativeOrder->status, [
                    CooperativeOrderStatus::CONFIRMED,
                    CooperativeOrderStatus::PREPARING,
                    CooperativeOrderStatus::READY_FOR_SHIPMENT,
                ], true)) {
                    throw new OrderFulfillmentBlocked('Only a confirmed, unshipped suborder can be cancelled for safety.');
                }

                $this->inventoryReservations->restoreAfterSafetyCancellation(
                    (string) $cooperativeOrder->getKey(),
                    hash('sha256', $holdId.'|safety-cancel|v1'),
                    trim($reason),
                    $actor->id,
                );

                $fromStatus = $cooperativeOrder->status->value;
                $cooperativeOrder->status = CooperativeOrderStatus::CANCELLED;
                $cooperativeOrder->safety_cancelled_at = now();
                $cooperativeOrder->safety_cancellation_reason = trim($reason);
                $cooperativeOrder->responded_at = now();
                $cooperativeOrder->save();

                OrderStatusHistory::query()->create([
                    'subject_type' => 'cooperative_order',
                    'subject_id' => (string) $cooperativeOrder->getKey(),
                    'from_status' => $fromStatus,
                    'to_status' => CooperativeOrderStatus::CANCELLED->value,
                    'actor_id' => $actor->id,
                    'actor_role' => $this->actorRole($actor),
                    'reason' => trim($reason),
                    'metadata' => [
                        'source' => 'order_safety_hold',
                        'safety_hold_id' => $holdId,
                    ],
                    'occurred_at' => now(),
                ]);

                $this->refreshOrderStatus((string) $cooperativeOrder->order_id);
            }

            $hold->status = 'resolved';
            $hold->resolved_by_id = $actor->id;
            $hold->resolved_by_type = $actor->actorType;
            $hold->resolution = $decision;
            $hold->resolution_reason = trim($reason);
            $hold->resolved_at = now();
            $hold->save();

            $this->audit->record(new AuditEntryDTO(
                eventKey: 'orders.safety_hold.resolved',
                action: 'resolve',
                actorType: $actor->actorType,
                actorId: $actor->id,
                cooperativeId: (string) $hold->cooperative_id,
                subjectType: 'order_safety_hold',
                subjectId: $holdId,
                oldValues: ['status' => 'active'],
                newValues: [
                    'status' => 'resolved',
                    'resolution' => $decision,
                    'reason' => trim($reason),
                ],
            ));
        }, 3);
    }

    private function authorizeResolution(ActorContext $actor, string $cooperativeId): void
    {
        if (! $actor->authorized || ! $actor->hasPermission('orders.safety_holds.resolve')) {
            throw new AuthorizationException('The actor cannot resolve this safety hold.');
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

    private function actorRole(ActorContext $actor): string
    {
        foreach (['super_admin_root', 'super_admin', 'cooperative_admin'] as $role) {
            if ($actor->hasRole($role)) {
                return $role;
            }
        }

        return 'system';
    }

    private function refreshOrderStatus(string $orderId): void
    {
        $order = Order::query()->whereKey($orderId)->lockForUpdate()->firstOrFail();
        $statuses = $order->cooperativeOrders()->pluck('status')->map(
            static fn ($status): string => $status instanceof CooperativeOrderStatus
                ? $status->value
                : (string) $status,
        );

        $total = $statuses->count();
        $confirmed = $statuses->filter(
            static fn (string $status): bool => $status === CooperativeOrderStatus::CONFIRMED->value,
        )->count();
        $cancelled = $statuses->filter(
            static fn (string $status): bool => $status === CooperativeOrderStatus::CANCELLED->value,
        )->count();
        $closed = $statuses->filter(
            static fn (string $status): bool => in_array($status, [
                CooperativeOrderStatus::REFUSED->value,
                CooperativeOrderStatus::EXPIRED->value,
                CooperativeOrderStatus::CANCELLED->value,
            ], true),
        )->count();

        $next = match (true) {
            $total > 0 && $cancelled === $total => OrderStatus::CANCELLED,
            $cancelled > 0 => OrderStatus::PARTIALLY_CANCELLED,
            $total > 0 && $closed === $total => OrderStatus::REFUSED,
            $confirmed > 0 && $closed > 0 => OrderStatus::PARTIALLY_REFUSED,
            $confirmed > 0 => OrderStatus::PARTIALLY_CONFIRMED,
            $closed > 0 => OrderStatus::PARTIALLY_REFUSED,
            default => $order->status,
        };

        if ($next === $order->status) {
            return;
        }

        $fromStatus = $order->status->value;
        $order->status = $next;
        $order->save();

        OrderStatusHistory::query()->create([
            'subject_type' => 'order',
            'subject_id' => (string) $order->getKey(),
            'from_status' => $fromStatus,
            'to_status' => $next->value,
            'actor_id' => null,
            'actor_role' => 'system',
            'reason' => 'Agrégation de l’annulation de sécurité d’une sous-commande.',
            'metadata' => ['source' => 'order_safety_hold', 'cooperative_order_id' => null],
            'occurred_at' => now(),
        ]);
    }

    public function assertCanShip(string $cooperativeOrderId): void
    {
        DB::transaction(function () use ($cooperativeOrderId): void {
            CooperativeOrder::query()
                ->whereKey($cooperativeOrderId)
                ->lockForUpdate()
                ->firstOrFail();

            $activeHold = OrderSafetyHold::query()
                ->where('cooperative_order_id', $cooperativeOrderId)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if ($activeHold !== null) {
                throw new OrderFulfillmentBlocked('Expédition interdite : un blocage de sécurité est actif sur cette sous-commande.');
            }
        }, 3);
    }
}
