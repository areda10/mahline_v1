<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Contracts;

use App\Domains\Inventory\Enums\InventoryStockableType;

interface InventoryStockUnitChangeGuard
{
    public function hasStockRecord(
        InventoryStockableType $stockableType,
        string $stockableId,
    ): bool;
}
