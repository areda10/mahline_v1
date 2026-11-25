<?php

declare(strict_types=1);

namespace App\Domains\Products\DTOs;

final readonly class InventoryStockableData
{
    public function __construct(
        public string $cooperativeId,
        public string $stockableType,
        public string $stockableId,
        public string $unitCode,
        public bool $isOperational,
    ) {}
}
