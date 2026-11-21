<?php

declare(strict_types=1);

namespace App\Domains\Identity\Notifications\Listeners;

use App\Domains\Inventory\Events\InventoryDomainEvent;
use App\Shared\Contracts\InventorySecurityReportNotificationDispatcher;

final class DispatchInventorySecurityReportNotification
{
    public function __construct(
        private readonly InventorySecurityReportNotificationDispatcher $dispatcher,
    ) {}

    public function handle(InventoryDomainEvent $event): void
    {
        $this->dispatcher->dispatch($event->eventKey, $event->payload);
    }
}
