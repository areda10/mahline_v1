<?php

declare(strict_types=1);

namespace App\Domains\Orders\Listeners;

use App\Domains\Inventory\Events\InventoryDomainEvent;
use App\Domains\Orders\Services\CooperativeOrderExpirationService;

final class HandleInventoryDomainEvent
{
    public function __construct(
        private readonly CooperativeOrderExpirationService $expirations,
    ) {}

    public function handle(InventoryDomainEvent $event): void
    {
        if ($event->eventKey !== 'inventory.reservation.expired') {
            return;
        }

        $cooperativeOrderId = $event->payload['cooperative_order_id'] ?? null;
        if (! is_string($cooperativeOrderId) || $cooperativeOrderId === '') {
            return;
        }

        $this->expirations->expireIfAwaiting($cooperativeOrderId);
    }
}
