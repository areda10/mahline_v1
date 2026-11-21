<?php

declare(strict_types=1);

namespace App\Domains\Inventory\DTOs;

use DateTimeImmutable;

final readonly class InventoryReservationResult
{
    public function __construct(
        public string $reservationId,
        public string $cooperativeOrderId,
        public string $status,
        public ?DateTimeImmutable $expiresAt,
    ) {}
}
