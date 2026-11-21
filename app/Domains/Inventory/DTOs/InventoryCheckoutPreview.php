<?php

declare(strict_types=1);

namespace App\Domains\Inventory\DTOs;

final readonly class InventoryCheckoutPreview
{
    /**
     * @param  list<InventoryCheckoutCooperativeResult>  $cooperatives
     */
    public function __construct(
        public array $cooperatives,
        public string $fingerprint,
    ) {}

    /** @return list<string> */
    public function unavailableCooperativeIds(): array
    {
        return array_values(array_map(
            static fn (InventoryCheckoutCooperativeResult $result): string => $result->cooperativeId,
            array_filter($this->cooperatives, static fn (InventoryCheckoutCooperativeResult $result): bool => ! $result->available),
        ));
    }
}
