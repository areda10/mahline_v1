<?php

declare(strict_types=1);

namespace App\Domains\Inventory\DTOs;

use App\Domains\Inventory\Enums\InventoryStockableType;

final readonly class InventoryReservationLineData
{
    public function __construct(
        public string $orderItemId,
        public InventoryStockableType $stockableType,
        public string $stockableId,
        public string $quantity,
        public string $unitCode,
    ) {}
}
