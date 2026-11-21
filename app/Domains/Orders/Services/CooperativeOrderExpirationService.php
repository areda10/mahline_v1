<?php

declare(strict_types=1);

namespace App\Domains\Orders\Services;

use App\Core\Foundation\Services\BaseService;
use App\Domains\Orders\Enums\CooperativeOrderStatus;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Models\CooperativeOrder;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderStatusHistory;
use Illuminate\Support\Facades\DB;

final class CooperativeOrderExpirationService extends BaseService
{
    public function expireIfAwaiting(string $cooperativeOrderId): void
    {
        DB::transaction(function () use ($cooperativeOrderId): void {
            $cooperativeOrder = CooperativeOrder::query()
                ->whereKey($cooperativeOrderId)
                ->lockForUpdate()
                ->first();

            if ($cooperativeOrder === null
                || $cooperativeOrder->status !== CooperativeOrderStatus::AWAITING_COOPERATIVE
                || $cooperativeOrder->response_deadline_at === null
                || $cooperativeOrder->response_deadline_at->isFuture()) {
                return;
            }

            $fromStatus = $cooperativeOrder->status->value;
            $cooperativeOrder->status = CooperativeOrderStatus::EXPIRED;
            $cooperativeOrder->responded_at = now();
            $cooperativeOrder->response_overdue_at = now();
            $cooperativeOrder->forceFill(['updated_by' => null])->save();

            OrderStatusHistory::query()->create([
                'subject_type' => 'cooperative_order',
                'subject_id' => (string) $cooperativeOrder->getKey(),
                'from_status' => $fromStatus,
                'to_status' => CooperativeOrderStatus::EXPIRED->value,
                'actor_id' => null,
                'actor_role' => 'system',
                'reason' => 'Expiration automatique de la sous-commande après 24 heures sans réponse.',
                'metadata' => ['source' => 'inventory.reservation.expired'],
                'occurred_at' => now(),
            ]);

            $order = Order::query()
                ->whereKey($cooperativeOrder->order_id)
                ->lockForUpdate()
                ->firstOrFail();

            $statuses = $order->cooperativeOrders()->pluck('status')->map(
                static fn ($status): string => $status instanceof CooperativeOrderStatus
                    ? $status->value
                    : (string) $status,
            );

            $confirmed = $statuses->filter(
                static fn (string $status): bool => $status === CooperativeOrderStatus::CONFIRMED->value,
            )->count();
            $closed = $statuses->filter(
                static fn (string $status): bool => in_array($status, [
                    CooperativeOrderStatus::REFUSED->value,
                    CooperativeOrderStatus::EXPIRED->value,
                ], true),
            )->count();
            $total = $statuses->count();

            $next = match (true) {
                $total > 0 && $confirmed === $total => OrderStatus::CONFIRMED,
                $total > 0 && $closed === $total => OrderStatus::REFUSED,
                $confirmed > 0 && $closed > 0 => OrderStatus::PARTIALLY_REFUSED,
                $confirmed > 0 => OrderStatus::PARTIALLY_CONFIRMED,
                $closed > 0 => OrderStatus::PARTIALLY_REFUSED,
                default => $order->status,
            };

            if ($next === $order->status) {
                return;
            }

            $fromOrderStatus = $order->status->value;
            $order->status = $next;
            $order->forceFill(['updated_by' => null])->save();

            OrderStatusHistory::query()->create([
                'subject_type' => 'order',
                'subject_id' => (string) $order->getKey(),
                'from_status' => $fromOrderStatus,
                'to_status' => $next->value,
                'actor_id' => null,
                'actor_role' => 'system',
                'reason' => 'Agrégation de l’expiration automatique d’une sous-commande.',
                'metadata' => ['source' => 'inventory.reservation.expired'],
                'occurred_at' => now(),
            ]);
        }, 3);
    }
}
