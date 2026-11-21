<?php

declare(strict_types=1);

namespace App\Domains\Inventory\DTOs;

final readonly class InventoryCheckoutCooperativeResult
{
    /**
     * @param  list<string>  $unavailableLineKeys
     */
    public function __construct(
        public string $cooperativeId,
        public bool $available,
        public array $unavailableLineKeys,
    ) {}
}
