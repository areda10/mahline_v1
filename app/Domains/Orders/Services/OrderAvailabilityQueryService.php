<?php

declare(strict_types=1);

namespace App\Domains\Orders\Services;

use App\Core\Foundation\Services\BaseService;
use App\Domains\Inventory\Contracts\InventoryOrderAvailabilityReader;
use App\Domains\Orders\Models\Order;
use App\Shared\Contracts\ActorContextProvider;
use App\Shared\Contracts\SuperAdminCooperativeAssignmentReader;
use App\Shared\DTOs\ActorContext;
use Illuminate\Auth\Access\AuthorizationException;

final class OrderAvailabilityQueryService extends BaseService
{
    public function __construct(
        private readonly InventoryOrderAvailabilityReader $inventory,
        private readonly ActorContextProvider $actors,
        private readonly SuperAdminCooperativeAssignmentReader $assignments,
    ) {}

    /** @return array<string, mixed> */
    public function forOrder(string $orderId, string $actorId): array
    {
        $order = Order::query()
            ->with(['cooperativeOrders.items'])
            ->findOrFail($orderId);
        $actor = $this->actors->forUserId($actorId);
        $this->authorizeOrder($actor, $order);

        $allSuborders = $order->cooperativeOrders;
        $isMultiCooperative = $allSuborders->count() > 1;
        $visibleSuborders = $allSuborders->filter(
            fn ($suborder): bool => $this->canViewCooperative($actor, (string) $suborder->cooperative_id),
        )->values();

        if ($visibleSuborders->isEmpty()) {
            throw new AuthorizationException('The actor cannot view availability for this order.');
        }

        $suborders = $visibleSuborders->map(function ($suborder) use ($isMultiCooperative): array {
            $inventory = $this->inventory->forSuborder((string) $suborder->getKey());
            $lineAvailability = collect($inventory->lines)->keyBy('orderItemId');

            $lines = $suborder->items->map(function ($item) use ($lineAvailability): array {
                $reservationLine = $lineAvailability->get((string) $item->getKey());
                $lineAvailable = $reservationLine !== null
                    && $reservationLine->available
                    && $reservationLine->quantity === (string) $item->quantity
                    && $reservationLine->unitCode === (string) $item->unit;

                return [
                    'order_item_id' => (string) $item->getKey(),
                    'available' => $lineAvailable,
                ];
            })->all();

            $available = $inventory->available
                && $lines !== []
                && collect($lines)->every(static fn (array $line): bool => $line['available']);

            return [
                'cooperative_order_id' => (string) $suborder->getKey(),
                'cooperative_id' => (string) $suborder->cooperative_id,
                'available' => $available,
                'reservation_status' => $inventory->reservationStatus,
                'lines' => $isMultiCooperative ? [] : $lines,
            ];
        })->all();

        return [
            'order_id' => (string) $order->getKey(),
            'is_multi_cooperative' => $isMultiCooperative,
            'global_available' => $isMultiCooperative ? null : $suborders[0]['available'],
            'suborders' => $suborders,
        ];
    }

    private function authorizeOrder(ActorContext $actor, Order $order): void
    {
        if (! $actor->authorized) {
            throw new AuthorizationException('The actor is not authorized to view order availability.');
        }

        if ($actor->hasRole('customer') && (string) $order->customer_id === $actor->id) {
            return;
        }

        if ($actor->hasRole('super_admin_root')) {
            return;
        }

        if ($actor->hasRole('super_admin') && $order->cooperativeOrders->contains(
            fn ($suborder): bool => $this->assignments->isAssigned($actor->id, (string) $suborder->cooperative_id),
        )) {
            return;
        }

        if (($actor->hasRole('cooperative_admin') || $actor->hasRole('user'))
            && $order->cooperativeOrders->contains(
                fn ($suborder): bool => $actor->cooperativeId === (string) $suborder->cooperative_id,
            )) {
            return;
        }

        throw new AuthorizationException('The actor is outside the order availability scope.');
    }

    private function canViewCooperative(ActorContext $actor, string $cooperativeId): bool
    {
        if ($actor->hasRole('customer') || $actor->hasRole('super_admin_root')) {
            return true;
        }

        if ($actor->hasRole('super_admin')) {
            return $this->assignments->isAssigned($actor->id, $cooperativeId);
        }

        return ($actor->hasRole('cooperative_admin') || $actor->hasRole('user'))
            && $actor->cooperativeId === $cooperativeId;
    }
}
