<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Console\Commands;

use App\Domains\Inventory\Contracts\InventoryReservationManager;
use App\Domains\Inventory\Models\InventoryReservation;
use Illuminate\Console\Command;

final class ExpireInventoryReservations extends Command
{
    protected $signature = 'inventory:expire-reservations {--limit=100}';

    protected $description = 'Release Inventory reservations that have passed their 24-hour deadline.';

    public function handle(InventoryReservationManager $reservations): int
    {
        $limit = max(1, min(1000, (int) $this->option('limit')));
        $cooperativeOrderIds = InventoryReservation::query()
            ->where('status', 'active')
            ->where('expires_at', '<=', now())
            ->orderBy('expires_at')
            ->limit($limit)
            ->pluck('cooperative_order_id');

        foreach ($cooperativeOrderIds as $cooperativeOrderId) {
            $reservations->releaseForSuborder(
                (string) $cooperativeOrderId,
                hash('sha256', (string) $cooperativeOrderId.'|expire|v1'),
                'Expiration automatique de la réservation après 24 heures sans réponse coopérative.',
                null,
                true,
            );
        }

        $this->info(sprintf('%d reservation(s) processed.', $cooperativeOrderIds->count()));

        return self::SUCCESS;
    }
}
