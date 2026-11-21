<?php

declare(strict_types=1);

namespace App\Domains\Orders\Contracts;

use App\Domains\Orders\DTOs\OrderCheckoutData;
use App\Domains\Orders\Models\Order;

interface OrderCheckoutCreator
{
    public function create(OrderCheckoutData $checkout): Order;
}
