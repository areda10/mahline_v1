<?php

declare(strict_types=1);

namespace App\Domains\Orders\Enums;

enum OrderStatus: string
{
    case AWAITING_COOPERATIVES = 'awaiting_cooperatives';
    case PARTIALLY_CONFIRMED = 'partially_confirmed';
    case CONFIRMED = 'confirmed';
    case PARTIALLY_REFUSED = 'partially_refused';
    case REFUSED = 'refused';
    case PREPARING = 'preparing';
    case PARTIALLY_SHIPPED = 'partially_shipped';
    case SHIPPED = 'shipped';
    case COMPLETED = 'completed';
    case PARTIALLY_CANCELLED = 'partially_cancelled';
    case CANCELLED = 'cancelled';
}