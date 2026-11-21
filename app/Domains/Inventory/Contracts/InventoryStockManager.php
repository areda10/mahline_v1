<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Contracts;

use App\Domains\Inventory\Enums\InventoryStockableType;

interface InventoryStockManager
{
    public function initialize(
        string $cooperativeId,
        InventoryStockableType $stockableType,
        string $stockableId,
        string $unitCode,
        string $quantity,
        string $reason,
        string $actorId,
        string $idempotencyKey,
    ): string;

    public function receive(
        string $cooperativeId,
        InventoryStockableType $stockableType,
        string $stockableId,
        string $quantity,
        string $reason,
        string $actorId,
        string $idempotencyKey,
        string $movementType = 'purchase_receipt',
    ): string;

    public function adjust(
        string $cooperativeId,
        InventoryStockableType $stockableType,
        string $stockableId,
        string $signedQuantity,
        string $reason,
        string $actorId,
        string $idempotencyKey,
    ): string;
}
