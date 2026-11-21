<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Contracts;

interface InventoryThresholdManager
{
    public function setLowStockThreshold(
        string $stockId,
        string $threshold,
        string $reason,
        string $actorId,
        string $idempotencyKey,
    ): void;
}
