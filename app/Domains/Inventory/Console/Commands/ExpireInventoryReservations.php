<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Console\Commands;

use App\Domains\Inventory\Contracts\InventoryReservationManager;
use App\Domains\Inventory\Models\InventoryReservation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class ExpireInventoryReservations extends Command
{
    protected $signature = 'inventory:expire-reservations {--limit=100}';

    protected $description = 'Release Inventory reservations that have passed their 24-hour deadline.';

    public function handle(InventoryReservationManager $reservations): int
    {
        $limit = max(1, min(1000, (int) $this->option('limit')));
        $reservations = InventoryReservation::query()
            ->where('status', 'active')
            ->where('expires_at', '<=', now())
            ->orderBy('expires_at')
            ->limit($limit)
            ->get(['id', 'cooperative_order_id']);

        $processed = 0;
        foreach ($reservations as $reservation) {
            $lockedByCount = DB::table('inventory_reservation_items as items')
                ->join('inventory_stocks as stocks', 'stocks.id', '=', 'items.stock_id')
                ->where('items.reservation_id', $reservation->getKey())
                ->whereNotNull('stocks.active_count_session_id')
                ->exists();

            if ($lockedByCount) {
                continue;
            }

            $cooperativeOrderId = $reservation->cooperative_order_id;
            $reservations->releaseForSuborder(
                (string) $cooperativeOrderId,
                hash('sha256', (string) $cooperativeOrderId.'|expire|v1'),
                'Expiration automatique de la réservation après 24 heures sans réponse coopérative.',
                null,
                true,
            );
            $processed++;
        }

        $this->info(sprintf('%d reservation(s) processed.', $processed));

        return self::SUCCESS;
    }
}
