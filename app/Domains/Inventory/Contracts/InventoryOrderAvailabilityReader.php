<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Contracts;

use App\Domains\Inventory\DTOs\InventorySuborderAvailability;

interface InventoryOrderAvailabilityReader
{
    public function forSuborder(string $cooperativeOrderId): InventorySuborderAvailability;
}
