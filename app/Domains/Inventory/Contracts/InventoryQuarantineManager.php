<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Contracts;

use App\Domains\Inventory\Enums\InventoryInspectionResult;
use App\Domains\Inventory\Enums\InventoryStockableType;

interface InventoryQuarantineManager
{
    public function receiveReturn(
        string $cooperativeId,
        InventoryStockableType $stockableType,
        string $stockableId,
        string $quantity,
        string $sourceType,
        string $sourceId,
        string $reason,
        string $actorId,
        string $idempotencyKey,
    ): string;

    public function inspect(
        string $quarantineId,
        InventoryInspectionResult $result,
        string $reason,
        string $actorId,
        string $idempotencyKey,
    ): void;

    public function authorizeSale(
        string $quarantineId,
        string $quantity,
        string $reason,
        string $actorId,
        string $idempotencyKey,
    ): string;

    public function revokeSale(
        string $authorizationId,
        string $reason,
        string $actorId,
        string $idempotencyKey,
    ): void;
}
