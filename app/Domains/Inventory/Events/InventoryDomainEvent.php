<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Events;

final readonly class InventoryDomainEvent
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $eventKey,
        public array $payload,
    ) {}
}
