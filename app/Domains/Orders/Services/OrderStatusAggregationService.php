<?php

declare(strict_types=1);

namespace App\Domains\Orders\Services;

use App\Domains\Identity\Users\Models\User;
use App\Domains\Orders\Enums\CooperativeOrderStatus;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderStatusHistory;
use Illuminate\Support\Collection;

final class OrderStatusAggregationService
{
    /**
     * Recalculate the global status from the lifecycle state of its cooperative
     * suborders. Refused and cancelled suborders are excluded from fulfillment
     * progress, while the remaining suborders continue independently.
     */
    public function refresh(Order $order, User $actor, string $reason): Order
    {
        $lockedOrder = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

        $statuses = $lockedOrder->cooperativeOrders()
            ->pluck('status')
            ->map(static fn ($status): string => $status instanceof CooperativeOrderStatus ? $status->value : (string) $status);

        $next = $this->aggregate($statuses);

        if ($next === $lockedOrder->status) {
            return $lockedOrder;
        }

        $previous = $lockedOrder->status;
        $lockedOrder->status = $next;

        if ($next === OrderStatus::CONFIRMED && $lockedOrder->confirmed_at === null) {
            $lockedOrder->confirmed_at = now();
        }

        if ($next === OrderStatus::CANCELLED && $lockedOrder->cancelled_at === null) {
            $lockedOrder->cancelled_at = now();
        }

        $lockedOrder->forceFill(['updated_by' => (string) $actor->getKey()])->save();

        OrderStatusHistory::query()->create([
            'subject_type' => 'order',
            'subject_id' => (string) $lockedOrder->getKey(),
            'from_status' => $previous->value,
            'to_status' => $next->value,
            'actor_id' => (string) $actor->getKey(),
            'actor_role' => $this->actorRole($actor),
            'reason' => $reason,
            'metadata' => ['workflow' => 'cooperative_suborder_aggregation'],
            'occurred_at' => now(),
        ]);

        return $lockedOrder->refresh();
    }

    /**
     * @param Collection<int, string> $statuses
     */
    private function aggregate(Collection $statuses): OrderStatus
    {
        $total = $statuses->count();

        if ($total === 0) {
            return OrderStatus::AWAITING_COOPERATIVES;
        }

        $cancelled = $statuses->filter(static fn (string $status): bool => $status === CooperativeOrderStatus::CANCELLED->value)->count();
        $refused = $statuses->filter(static fn (string $status): bool => in_array($status, [
            CooperativeOrderStatus::REFUSED->value,
            CooperativeOrderStatus::EXPIRED->value,
        ], true))->count();

        $executable = $statuses->reject(static fn (string $status): bool => in_array($status, [
            CooperativeOrderStatus::REFUSED->value,
            CooperativeOrderStatus::CANCELLED->value,
        ], true));

        if ($executable->isEmpty()) {
            return $cancelled === $total
                ? OrderStatus::CANCELLED
                : ($cancelled > 0 ? OrderStatus::PARTIALLY_CANCELLED : OrderStatus::REFUSED);
        }

        $count = $executable->count();
        $shippedOrDelivered = $executable->filter(static fn (string $status): bool => in_array($status, [
            CooperativeOrderStatus::SHIPPED->value,
            CooperativeOrderStatus::DELIVERED->value,
        ], true))->count();
        $delivered = $executable->filter(static fn (string $status): bool => $status === CooperativeOrderStatus::DELIVERED->value)->count();

        if ($delivered === $count) {
            return OrderStatus::COMPLETED;
        }

        if ($shippedOrDelivered === $count) {
            return OrderStatus::SHIPPED;
        }

        if ($shippedOrDelivered > 0) {
            return OrderStatus::PARTIALLY_SHIPPED;
        }

        if ($executable->contains(static fn (string $status): bool => in_array($status, [
            CooperativeOrderStatus::PREPARING->value,
            CooperativeOrderStatus::READY_FOR_SHIPMENT->value,
        ], true))) {
            return OrderStatus::PREPARING;
        }

        $confirmed = $executable->filter(static fn (string $status): bool => in_array($status, [
            CooperativeOrderStatus::CONFIRMED->value,
            CooperativeOrderStatus::PREPARING->value,
            CooperativeOrderStatus::READY_FOR_SHIPMENT->value,
        ], true))->count();

        if ($confirmed === $count) {
            return OrderStatus::CONFIRMED;
        }

        if ($confirmed > 0 || $refused > 0) {
            return $cancelled > 0
                ? OrderStatus::PARTIALLY_CANCELLED
                : ($refused > 0 ? OrderStatus::PARTIALLY_REFUSED : OrderStatus::PARTIALLY_CONFIRMED);
        }

        return $cancelled > 0
            ? OrderStatus::PARTIALLY_CANCELLED
            : ($refused > 0 ? OrderStatus::PARTIALLY_REFUSED : OrderStatus::AWAITING_COOPERATIVES);
    }

    private function actorRole(User $actor): string
    {
        foreach (['super_admin_root', 'super_admin', 'cooperative_admin', 'user', 'customer'] as $role) {
            if ($actor->hasRole($role)) {
                return $role;
            }
        }

        return 'system';
    }
}
