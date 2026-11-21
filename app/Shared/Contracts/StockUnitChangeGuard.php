<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

interface StockUnitChangeGuard
{
    public function hasStockRecord(string $stockableType, string $stockableId): bool;
}
