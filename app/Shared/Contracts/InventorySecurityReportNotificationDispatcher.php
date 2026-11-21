<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

interface InventorySecurityReportNotificationDispatcher
{
    /** @param array<string, mixed> $payload */
    public function dispatch(string $eventKey, array $payload): void;
}
