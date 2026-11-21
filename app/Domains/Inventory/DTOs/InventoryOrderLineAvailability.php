<?php

declare(strict_types=1);

namespace App\Domains\Inventory\DTOs;

final readonly class InventoryOrderLineAvailability
{
    public function __construct(
        public string $orderItemId,
        public string $quantity,
        public string $unitCode,
        public bool $available,
    ) {}
}
