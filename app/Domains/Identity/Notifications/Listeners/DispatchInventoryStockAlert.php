<?php

declare(strict_types=1);

namespace App\Domains\Identity\Notifications\Listeners;

use App\Domains\Inventory\Events\InventoryDomainEvent;
use App\Shared\Contracts\InventoryAlertNotificationDispatcher;

final class DispatchInventoryStockAlert
{
    public function __construct(
        private readonly InventoryAlertNotificationDispatcher $dispatcher,
    ) {}

    public function handle(InventoryDomainEvent $event): void
    {
        $this->dispatcher->dispatch($event->eventKey, $event->payload);
    }
}
