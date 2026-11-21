<?php

declare(strict_types=1);

namespace App\Domains\Identity\Notifications\Listeners;

use App\Domains\Identity\Notifications\InventoryStockAlertNotificationDispatcher;
use App\Domains\Inventory\Events\InventoryDomainEvent;

final class DispatchInventoryStockAlert
{
    public function __construct(
        private readonly InventoryStockAlertNotificationDispatcher $dispatcher,
    ) {}

    public function handle(InventoryDomainEvent $event): void
    {
        $this->dispatcher->dispatch($event->eventKey, $event->payload);
    }
}
