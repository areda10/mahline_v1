<?php

declare(strict_types=1);

namespace App\Domains\Products\Contracts;

use App\Domains\Products\DTOs\CheckoutProductData;

interface CheckoutCatalog
{
    /**
     * Resolve authoritative product data while retaining row locks in the caller's transaction.
     */
    public function resolveForCheckout(string $productId, ?string $variantId): ?CheckoutProductData;
}
