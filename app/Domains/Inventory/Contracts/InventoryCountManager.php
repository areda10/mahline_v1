<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Contracts;

interface InventoryCountManager
{
    /** @param list<string> $stockIds */
    public function createSession(
        string $cooperativeId,
        array $stockIds,
        string $actorId,
        string $idempotencyKey,
    ): string;

    public function recordCount(
        string $countItemId,
        string $countedQuantity,
        ?string $reason,
        string $actorId,
        ?string $expectedSessionId = null,
    ): void;

    public function validateSession(string $sessionId, string $actorId): void;

    public function cancelSession(
        string $sessionId,
        string $reason,
        string $actorId,
    ): void;
}
