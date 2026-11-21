<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Contracts;

use App\Domains\Inventory\Models\InventoryCountSession;
use Illuminate\Database\Eloquent\Collection;

interface InventoryCountManager
{
    /** @return Collection<int, InventoryCountSession> */
    public function sessionsForCooperative(string $cooperativeId, string $actorId): Collection;

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
    ): void;

    public function validateSession(string $sessionId, string $actorId): void;

    public function cancelSession(
        string $sessionId,
        string $reason,
        string $actorId,
    ): void;
}
