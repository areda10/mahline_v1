<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Services\Stock;

use App\Core\Foundation\Services\BaseService;
use App\Domains\Inventory\Contracts\InventoryStockUnitChangeGuard;
use App\Domains\Inventory\Enums\InventoryStockableType;
use App\Domains\Inventory\Models\InventoryStock;

final class InventoryStockUnitChangeGuardService extends BaseService implements InventoryStockUnitChangeGuard
{
    public function hasStockRecord(
        InventoryStockableType $stockableType,
        string $stockableId,
    ): bool {
        return InventoryStock::query()
            ->where('stockable_type', $stockableType->value)
            ->where('stockable_id', $stockableId)
            ->exists();
    }
}
