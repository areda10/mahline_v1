<?php

declare(strict_types=1);

namespace App\Domains\Orders\Services;

use App\Domains\Identity\Users\Models\User;
use App\Domains\Orders\Enums\CooperativeOrderStatus;
use App\Domains\Orders\Enums\OrderItemStatus;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Models\CooperativeOrder;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderItem;
use App\Domains\Orders\Models\OrderStatusHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CustomerOrderCancellationService
{
    public function cancel(CooperativeOrder $cooperativeOrder, User $customer, string $reason): CooperativeOrder
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => ['Le motif de l’annulation est obligatoire.'],
            ]);
        }

        return DB::transaction(function () use ($cooperativeOrder, $customer, $reason): CooperativeOrder {
            // Lock order rows in the same order as the cooperative response workflow:
            // suborder first, then parent order. This serializes a concurrent confirm/cancel.
            $locked = CooperativeOrder::query()
                ->whereKey($cooperativeOrder->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $order = Order::query()
                ->whereKey($locked->order_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((string) $order->customer_id !== (string) $customer->getKey()) {
                abort(404);
            }

            if ($locked->status !== CooperativeOrderStatus::AWAITING_COOPERATIVE) {
                throw ValidationException::withMessages([
                    'cooperative_order' => ['Cette sous-commande ne peut plus être annulée par le client : la coopérative a déjà pris une décision.'],
                ]);
            }

            $fromStatus = $locked->status->value;
            $locked->status = CooperativeOrderStatus::CANCELLED;
            $locked->forceFill(['updated_by' => (string) $customer->getKey()])->save();

            OrderItem::query()
                ->where('cooperative_order_id', (string) $locked->getKey())
                ->where('status', OrderItemStatus::ACTIVE->value)
                ->update([
                    'status' => OrderItemStatus::CANCELLED->value,
                    'cancelled_at' => now(),
                    'cancellation_reason' => $reason,
                    'updated_at' => now(),
                    'updated_by' => (string) $customer->getKey(),
                ]);

            OrderStatusHistory::query()->create([
                'subject_type' => 'cooperative_order',
                'subject_id' => (string) $locked->getKey(),
                'from_status' => $fromStatus,
                'to_status' => CooperativeOrderStatus::CANCELLED->value,
                'actor_id' => (string) $customer->getKey(),
                'actor_role' => 'customer',
                'reason' => $reason,
                'metadata' => ['action' => 'customer_cancellation'],
                'occurred_at' => now(),
            ]);

            $remaining = $order->cooperativeOrders()
                ->whereNotIn('status', [
                    CooperativeOrderStatus::CANCELLED->value,
                    CooperativeOrderStatus::REFUSED->value,
                    CooperativeOrderStatus::EXPIRED->value,
                ])
                ->get(['items_subtotal', 'delivery_fee']);

            $itemsSubtotal = $remaining->sum(static fn (CooperativeOrder $item): float => (float) $item->items_subtotal);
            $deliveryTotal = $remaining->sum(static fn (CooperativeOrder $item): float => (float) $item->delivery_fee);

            $order->items_subtotal = number_format($itemsSubtotal, 2, '.', '');
            $order->delivery_total = number_format($deliveryTotal, 2, '.', '');
            $order->grand_total = number_format($itemsSubtotal + $deliveryTotal, 2, '.', '');

            $statuses = $order->cooperativeOrders()->pluck('status')->map(
                static fn ($status): string => $status instanceof CooperativeOrderStatus ? $status->value : (string) $status,
            );
            $total = $statuses->count();
            $cancelled = $statuses->filter(static fn (string $status): bool => $status === CooperativeOrderStatus::CANCELLED->value)->count();
            $confirmed = $statuses->filter(static fn (string $status): bool => $status === CooperativeOrderStatus::CONFIRMED->value)->count();
            $closed = $statuses->filter(static fn (string $status): bool => in_array($status, [
                CooperativeOrderStatus::REFUSED->value,
                CooperativeOrderStatus::EXPIRED->value,
                CooperativeOrderStatus::CANCELLED->value,
            ], true))->count();

            $nextStatus = match (true) {
                $total > 0 && $cancelled === $total => OrderStatus::CANCELLED,
                $cancelled > 0 => OrderStatus::PARTIALLY_CANCELLED,
                $total > 0 && $confirmed === $total => OrderStatus::CONFIRMED,
                $total > 0 && $closed === $total => OrderStatus::REFUSED,
                $confirmed > 0 && $closed > 0 => OrderStatus::PARTIALLY_REFUSED,
                $confirmed > 0 => OrderStatus::PARTIALLY_CONFIRMED,
                $closed > 0 => OrderStatus::PARTIALLY_REFUSED,
                default => $order->status,
            };

            $previousOrderStatus = $order->status->value;
            $order->status = $nextStatus;

            if ($nextStatus === OrderStatus::CANCELLED) {
                $order->cancelled_at = now();
                $order->cancellation_reason = $reason;
            }

            $order->forceFill(['updated_by' => (string) $customer->getKey()])->save();

            if ($previousOrderStatus !== $nextStatus->value) {
                OrderStatusHistory::query()->create([
                    'subject_type' => 'order',
                    'subject_id' => (string) $order->getKey(),
                    'from_status' => $previousOrderStatus,
                    'to_status' => $nextStatus->value,
                    'actor_id' => (string) $customer->getKey(),
                    'actor_role' => 'customer',
                    'reason' => $reason,
                    'metadata' => ['action' => 'customer_suborder_cancellation'],
                    'occurred_at' => now(),
                ]);
            }

            return $locked->refresh();
        }, 3);
    }
}
