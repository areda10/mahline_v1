<?php

declare(strict_types=1);

namespace App\Domains\Orders\Services;

use App\Domains\Identity\Users\Models\User;
use App\Domains\Orders\Enums\CooperativeOrderStatus;
use App\Domains\Orders\Models\CooperativeOrder;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderStatusHistory;
use App\Domains\Orders\Notifications\OrderReadyForShipmentNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CooperativeOrderFulfillmentService
{
    public function __construct(
        private readonly OrderStatusAggregationService $statusAggregation,
    ) {}

    public function startPreparation(CooperativeOrder $cooperativeOrder, User $actor): CooperativeOrder
    {
        return $this->transition($cooperativeOrder, $actor, CooperativeOrderStatus::CONFIRMED, CooperativeOrderStatus::PREPARING);
    }

    public function markReadyForShipment(CooperativeOrder $cooperativeOrder, User $actor): CooperativeOrder
    {
        return $this->transition($cooperativeOrder, $actor, CooperativeOrderStatus::PREPARING, CooperativeOrderStatus::READY_FOR_SHIPMENT);
    }

    private function transition(
        CooperativeOrder $cooperativeOrder,
        User $actor,
        CooperativeOrderStatus $from,
        CooperativeOrderStatus $to,
    ): CooperativeOrder {
        abort_unless($actor->hasRole('user'), 403);
        abort_unless($actor->cooperative_id !== null
            && (string) $actor->cooperative_id === (string) $cooperativeOrder->cooperative_id, 403);

        return DB::transaction(function () use ($cooperativeOrder, $actor, $from, $to): CooperativeOrder {
            $locked = CooperativeOrder::query()
                ->whereKey($cooperativeOrder->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== $from) {
                throw ValidationException::withMessages([
                    'cooperative_order' => ['Cette transition de préparation n’est pas autorisée pour le statut actuel.'],
                ]);
            }

            $locked->status = $to;
            $locked->forceFill(['updated_by' => (string) $actor->getKey()])->save();

            OrderStatusHistory::query()->create([
                'subject_type' => 'cooperative_order',
                'subject_id' => (string) $locked->getKey(),
                'from_status' => $from->value,
                'to_status' => $to->value,
                'actor_id' => (string) $actor->getKey(),
                'actor_role' => 'user',
                'reason' => null,
                'metadata' => ['workflow' => 'cooperative_fulfillment'],
                'occurred_at' => now(),
            ]);

            $this->statusAggregation->refresh(
                Order::query()->findOrFail($locked->order_id),
                $actor,
                'Agrégation après transition de préparation de la sous-commande.',
            );

            if ($to === CooperativeOrderStatus::READY_FOR_SHIPMENT) {
                $customer = $locked->order()->with('customer')->firstOrFail()->customer;
                $customer?->notify(new OrderReadyForShipmentNotification($locked));
            }

            return $locked->refresh();
        }, 3);
    }
}
