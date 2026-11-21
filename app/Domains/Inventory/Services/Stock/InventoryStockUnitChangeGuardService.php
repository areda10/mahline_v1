<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Services\Stock;

use App\Core\Foundation\Services\BaseService;
use App\Domains\Inventory\Models\InventoryStock;
use App\Shared\Contracts\StockUnitChangeGuard;

final class InventoryStockUnitChangeGuardService extends BaseService implements StockUnitChangeGuard
{
    public function hasStockRecord(
        string $stockableType,
        string $stockableId,
    ): bool {
        return InventoryStock::query()
            ->where('stockable_type', $stockableType)
            ->where('stockable_id', $stockableId)
            ->exists();
    }
}
