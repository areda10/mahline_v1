<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Enums;

enum InventorySecurityReportUrgency: string
{
    case NORMAL = 'normal';
    case URGENT = 'urgent';
}
