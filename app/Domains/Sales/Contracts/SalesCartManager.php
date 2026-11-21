<?php

declare(strict_types=1);

namespace App\Domains\Sales\Contracts;

use App\Domains\Sales\Models\SalesCart;
use App\Domains\Sales\Models\SalesCartItem;

interface SalesCartManager
{
    /** @return array{cart: SalesCart, guest_token: string} */
    public function createGuestCart(): array;

    public function getOrCreateCustomerCart(string $customerId): SalesCart;

    public function findGuestCart(string $guestToken): ?SalesCart;

    public function addItem(
        string $cartId,
        string $productId,
        ?string $variantId,
        string|int|float $quantity,
        int $expectedVersion,
    ): SalesCartItem;

    public function changeQuantity(
        string $cartId,
        string $itemId,
        string|int|float $quantity,
        int $expectedVersion,
    ): SalesCartItem;

    public function removeItem(string $cartId, string $itemId, int $expectedVersion): void;

    public function clear(string $cartId, int $expectedVersion): void;

    public function mergeGuestCart(string $guestToken, string $customerId): SalesCart;
}
