<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Console\Commands;

use App\Domains\Inventory\Models\InventoryStock;
use App\Domains\Inventory\Services\Alerts\InventoryAlertEvaluator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class ReconcileInventoryAlerts extends Command
{
    protected $signature = 'inventory:reconcile-alerts {--chunk=200}';

    protected $description = 'Reconcile low-stock and out-of-stock alert episodes against current balances.';

    public function handle(InventoryAlertEvaluator $alerts): int
    {
        $processed = 0;
        $chunk = max(1, min(1000, (int) $this->option('chunk')));

        InventoryStock::query()->orderBy('id')->chunkById($chunk, function ($stocks) use ($alerts, &$processed): void {
            foreach ($stocks as $stock) {
                DB::transaction(function () use ($alerts, $stock): void {
                    $alerts->evaluate($stock);
                }, 3);
                $processed++;
            }
        });

        $this->info(sprintf('%d stock balance(s) reconciled.', $processed));

        return self::SUCCESS;
    }
}
