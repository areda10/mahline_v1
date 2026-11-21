<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Contracts;

use App\Domains\Inventory\DTOs\InventoryCheckoutLineData;
use App\Domains\Inventory\DTOs\InventoryCheckoutPreview;

interface InventoryCheckoutAvailabilityReader
{
    /**
     * @param  list<InventoryCheckoutLineData>  $lines
     */
    public function preview(array $lines): InventoryCheckoutPreview;
}
