<?php

declare(strict_types=1);

namespace App\Domains\Products\Contracts;

use App\Domains\Products\DTOs\CheckoutProductData;
use App\Domains\Products\DTOs\InventoryStockableData;

interface CheckoutCatalog
{
    /**
     * Resolve authoritative product data while retaining row locks in the caller's transaction.
     */
    public function resolveForCheckout(string $productId, ?string $variantId): ?CheckoutProductData;

    public function resolveForInventory(string $stockableType, string $stockableId): ?InventoryStockableData;
}
