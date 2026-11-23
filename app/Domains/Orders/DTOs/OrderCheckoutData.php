<?php

declare(strict_types=1);

namespace App\Domains\Orders\DTOs;

final readonly class OrderCheckoutData
{
    /**
     * @param  array<string, mixed>  $deliveryAddressSnapshot
     * @param  list<array{product_id: string, product_variant_id?: string|null, cooperative_id?: string, accepted_unit_price?: string, quantity: string|int|float}>  $items
     */
    public function __construct(
        public string $customerId,
        public string $idempotencyKey,
        public array $deliveryAddressSnapshot,
        public array $items,
        public array $deliveryQuotes,
        public ?string $acceptedInventoryFingerprint = null,
    ) {}
}
