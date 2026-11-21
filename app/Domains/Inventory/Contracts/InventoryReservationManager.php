<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Contracts;

use App\Domains\Inventory\DTOs\InventoryReservationLineData;
use App\Domains\Inventory\DTOs\InventoryReservationResult;

interface InventoryReservationManager
{
    /**
     * @param list<InventoryReservationLineData> $items
     */
    public function reserveForSuborder(
        string $cooperativeOrderId,
        string $cooperativeId,
        array $items,
        string $idempotencyKey,
        string $actorId,
    ): InventoryReservationResult;

    public function consumeForSuborder(
        string $cooperativeOrderId,
        string $idempotencyKey,
        string $actorId,
    ): InventoryReservationResult;

    public function releaseForSuborder(
        string $cooperativeOrderId,
        string $idempotencyKey,
        string $reason,
        ?string $actorId,
        bool $expired = false,
    ): ?InventoryReservationResult;
}
