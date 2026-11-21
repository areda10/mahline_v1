<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Enums;

enum InventoryReservationStatus: string
{
    case ACTIVE = 'active';
    case CONSUMED = 'consumed';
    case RELEASED = 'released';
    case EXPIRED = 'expired';
    case SAFETY_RETURNED = 'safety_returned';
}
