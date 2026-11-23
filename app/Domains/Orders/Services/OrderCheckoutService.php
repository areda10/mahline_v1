<?php

declare(strict_types=1);

namespace App\Domains\Orders\Services;

use App\Domains\Identity\Users\Models\User;
use App\Domains\Orders\Contracts\OrderCheckoutCreator;
use App\Domains\Orders\DTOs\OrderCheckoutData;
use App\Domains\Orders\Models\Order;

final class OrderCheckoutService implements OrderCheckoutCreator
{
    public function __construct(
        private readonly OrderCreationService $orders,
    ) {}

    public function create(OrderCheckoutData $checkout): Order
    {
        $customer = User::query()->findOrFail($checkout->customerId);

        return $this->orders->createFromCheckout(
            $customer,
            [
                'delivery_address_snapshot' => $checkout->deliveryAddressSnapshot,
                'items' => $checkout->items,
                'delivery_quotes' => $checkout->deliveryQuotes,
                'idempotency_key' => $checkout->idempotencyKey,
            ],
            $checkout->idempotencyKey,
            $checkout->acceptedInventoryFingerprint,
        );
    }
}
