<?php

declare(strict_types=1);

namespace App\Domains\Inventory\DTOs;

final readonly class InventorySuborderAvailability
{
    /**
     * @param  list<InventoryOrderLineAvailability>  $lines
     */
    public function __construct(
        public string $cooperativeOrderId,
        public ?string $reservationStatus,
        public bool $available,
        public array $lines,
    ) {}
}
