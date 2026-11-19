<?php

declare(strict_types=1);

namespace App\Domains\Products\DTOs;

final readonly class CheckoutProductData
{
    public function __construct(
        public string $productId,
        public string $cooperativeId,
        public string $productName,
        public string $productSlug,
        public string $currency,
        public ?string $effectivePrice,
        public bool $hasVariants,
        public bool $hasParentUnit,
        public bool $variantRequested,
        public bool $variantFound,
        public ?string $variantId,
        public ?string $variantLabel,
        public ?string $variantSku,
        public ?string $unitCode,
        public ?string $unitLabel,
    ) {}
}
