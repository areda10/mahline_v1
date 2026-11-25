<?php

declare(strict_types=1);

namespace App\Domains\Orders\Services;

use App\Domains\Identity\Users\Models\User;
use App\Domains\Orders\Enums\CooperativeOrderStatus;
use App\Domains\Orders\Enums\OrderItemStatus;
use App\Domains\Orders\Models\CooperativeOrder;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderItem;
use App\Domains\Orders\Models\OrderStatusHistory;
use App\Domains\Orders\Notifications\CooperativeOrderWorkflowNotification;
use App\Domains\Orders\Notifications\CustomerOrderCancelledNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CustomerOrderCancellationService
{
    public function __construct(
        private readonly OrderStatusAggregationService $statusAggregation,
    ) {}

    public function cancel(CooperativeOrder $cooperativeOrder, User $customer, string $reason): CooperativeOrder
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => ['Le motif de l’annulation est obligatoire.'],
            ]);
        }

        $cancelledSuborder = DB::transaction(function () use ($cooperativeOrder, $customer, $reason): CooperativeOrder {
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

            $itemsSubtotalCents = $remaining->sum(fn (CooperativeOrder $item): int => $this->moneyToCents((string) $item->items_subtotal));
            $deliveryTotalCents = $remaining->sum(fn (CooperativeOrder $item): int => $this->moneyToCents((string) $item->delivery_fee));

            $order->items_subtotal = $this->formatMoney($itemsSubtotalCents);
            $order->delivery_total = $this->formatMoney($deliveryTotalCents);
            $order->grand_total = $this->formatMoney($itemsSubtotalCents + $deliveryTotalCents);

            $order->forceFill(['updated_by' => (string) $customer->getKey()])->save();

            $this->statusAggregation->refresh(
                $order,
                $customer,
                'Annulation client d’une sous-commande encore en attente.',
            );

            return $locked->refresh();
        }, 3);

        $cancelledSuborder->load(['order', 'cooperative']);
        $customer->notify(new CustomerOrderCancelledNotification($cancelledSuborder));

        User::query()
            ->where('cooperative_id', (string) $cancelledSuborder->cooperative_id)
            ->whereHas('roles', static fn ($query) => $query->where('slug', 'cooperative_admin'))
            ->get()
            ->each(fn (User $recipient) => $recipient->notify(new CooperativeOrderWorkflowNotification(
                $cancelledSuborder,
                'order.cancelled_by_customer',
                'Sous-commande annulée par le client',
                'Le client a annulé cette sous-commande avant votre décision.',
            )));

        return $cancelledSuborder;
    }

    private function moneyToCents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    private function formatMoney(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
