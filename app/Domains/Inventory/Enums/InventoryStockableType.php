<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Enums;

enum InventoryStockableType: string
{
    case PRODUCT = 'product';
    case VARIANT = 'variant';
}
