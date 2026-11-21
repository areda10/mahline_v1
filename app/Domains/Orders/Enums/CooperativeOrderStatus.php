<?php

declare(strict_types=1);

namespace App\Domains\Orders\Enums;

enum CooperativeOrderStatus: string
{
    case AWAITING_COOPERATIVE = 'awaiting_cooperative';
    case CONFIRMED = 'confirmed';
    case REFUSED = 'refused';
    case EXPIRED = 'expired';
    case PREPARING = 'preparing';
    case READY_FOR_SHIPMENT = 'ready_for_shipment';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';
    case RETURNED = 'returned';
    case CANCELLED = 'cancelled';
}
