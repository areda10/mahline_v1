<?php

declare(strict_types=1);

namespace App\Domains\Orders\Contracts;

interface OrderSafetyHoldManager
{
    public function raiseHold(
        string $cooperativeOrderId,
        string $sourceType,
        string $sourceId,
        string $reason,
        ?string $actorId = null,
        ?string $actorType = 'system',
    ): string;

    public function assertCanShip(string $cooperativeOrderId): void;

    public function resolveHold(
        string $holdId,
        string $decision,
        string $reason,
        string $actorId,
    ): void;
}
