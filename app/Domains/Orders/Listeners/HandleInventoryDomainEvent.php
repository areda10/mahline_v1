<?php

declare(strict_types=1);

namespace App\Domains\Orders\Listeners;

use App\Domains\Inventory\Events\InventoryDomainEvent;
use App\Domains\Orders\Contracts\OrderSafetyHoldManager;
use App\Domains\Orders\Services\CooperativeOrderExpirationService;

final class HandleInventoryDomainEvent
{
    public function __construct(
        private readonly OrderSafetyHoldManager $safetyHolds,
    ) {}

    public function handle(InventoryDomainEvent $event): void
    {
        if ($event->eventKey === 'inventory.reservation.expired') {
            return;
        }

        if ($event->eventKey !== 'inventory.quarantine.authorization.revoked') {
            return;
        }

        $authorizationId = $event->payload['authorization_id'] ?? null;
        $cooperativeOrderIds = $event->payload['affected_cooperative_order_ids'] ?? [];
        if (! is_string($authorizationId) || ! is_array($cooperativeOrderIds)) {
            return;
        }

        foreach ($cooperativeOrderIds as $cooperativeOrderId) {
            if (! is_string($cooperativeOrderId) || $cooperativeOrderId === '') {
                continue;
            }

            $this->safetyHolds->raiseHold(
                $cooperativeOrderId,
                'inventory_quarantine_authorization',
                $authorizationId,
                'Autorisation de vente en quarantaine révoquée après confirmation de la sous-commande. Examen de sécurité obligatoire avant expédition.',
            );
        }
    }
}
