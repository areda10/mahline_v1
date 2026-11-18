<?php

declare(strict_types=1);

namespace App\Domains\Orders\Enums;

enum OrderItemStatus: string
{
    case ACTIVE = 'active';
    case CANCELLED = 'cancelled';
    case REPLACED = 'replaced';
}