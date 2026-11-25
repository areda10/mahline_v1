<?php

declare(strict_types=1);

namespace App\Domains\Orders\Http\Controllers;

use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\CooperativeOrder;
use App\Domains\Orders\Services\CustomerOrderCancellationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class CustomerOrderHistoryController
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', 'string', 'in:'.implode(',', array_map(
                static fn (OrderStatus $status): string => $status->value,
                OrderStatus::cases(),
            ))],
        ]);

        $orders = Order::query()
            ->where('customer_id', (string) $request->user()->getKey())
            ->withCount('cooperativeOrders')
            ->when($filters['q'] ?? null, function ($query, string $search): void {
                $search = trim($search);
                $query->where(function ($nested) use ($search): void {
                    $nested->where('reference', 'like', '%'.$search.'%')
                        ->orWhereHas('cooperativeOrders', fn ($suborders) => $suborders
                            ->where('reference', 'like', '%'.$search.'%'));
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->orderByDesc('placed_at')
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        return view('customer.orders.index', [
            'orders' => $orders,
            'filters' => $filters,
            'statusLabels' => $this->statusLabels(),
        ]);
    }

    public function show(Request $request, string $orderId): View
    {
        // Scope the lookup to the authenticated customer: another customer's
        // order is indistinguishable from a missing order (404).
        $order = Order::query()
            ->where('customer_id', (string) $request->user()->getKey())
            ->whereKey($orderId)
            ->with([
                'statusHistories' => fn ($query) => $query->orderBy('occurred_at'),
                'cooperativeOrders' => fn ($query) => $query
                    ->orderBy('created_at')
                    ->with([
                        'cooperative',
                        'items' => fn ($items) => $items
                            ->with('changeProposals')
                            ->orderBy('created_at'),
                        'statusHistories' => fn ($history) => $history->orderBy('occurred_at'),
                    ]),
            ])
            ->firstOrFail();

        return view('customer.orders.show', [
            'order' => $order,
            'statusLabels' => $this->statusLabels(),
        ]);
    }

    public function cancelSuborder(Request $request, string $orderId, string $cooperativeOrderId, CustomerOrderCancellationService $cancellations)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        $order = Order::query()
            ->where('customer_id', (string) $request->user()->getKey())
            ->whereKey($orderId)
            ->firstOrFail();

        $suborder = CooperativeOrder::query()
            ->where('order_id', (string) $order->getKey())
            ->whereKey($cooperativeOrderId)
            ->firstOrFail();

        $cancellations->cancel($suborder, $request->user(), $data['reason']);

        return redirect()
            ->route('customer.orders.show', $order)
            ->with('success', 'La sous-commande a été annulée. Les frais et le total de la commande ont été recalculés.');
    }

    /**
     * @return array<string, string>
     */
    private function statusLabels(): array
    {
        return [
            'awaiting_cooperatives' => 'En attente des coopératives',
            'partially_confirmed' => 'Partiellement confirmée',
            'confirmed' => 'Confirmée',
            'partially_refused' => 'Partiellement refusée',
            'refused' => 'Refusée',
            'preparing' => 'En préparation',
            'partially_shipped' => 'Partiellement expédiée',
            'shipped' => 'Expédiée',
            'completed' => 'Terminée',
            'partially_cancelled' => 'Partiellement annulée',
            'cancelled' => 'Annulée',
            'awaiting_cooperative' => 'En attente de la coopérative',
            'expired' => 'Délai de réponse expiré',
            'ready_for_shipment' => 'Prête à expédier',
            'delivered' => 'Livrée',
            'returned' => 'Retournée',
            'active' => 'Active',
            'cancelled_item' => 'Annulée',
            'replaced' => 'Remplacée',
            'pending_customer' => 'En attente de votre réponse',
            'accepted' => 'Acceptée',
            'rejected' => 'Refusée',
            'expired_proposal' => 'Expirée',
        ];
    }
}
