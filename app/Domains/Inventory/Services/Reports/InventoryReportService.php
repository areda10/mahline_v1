<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Services\Reports;

use App\Core\Foundation\Services\BaseService;
use App\Domains\Inventory\Models\InventoryAlertEpisode;
use App\Domains\Inventory\Models\InventoryCountSession;
use App\Domains\Inventory\Models\InventoryMovement;
use App\Domains\Inventory\Models\InventoryQuarantine;
use App\Domains\Inventory\Models\InventoryStock;
use App\Domains\Inventory\Models\InventoryStockThreshold;
use App\Shared\Contracts\ActorContextProvider;
use App\Shared\Contracts\SuperAdminCooperativeAssignmentReader;
use App\Shared\DTOs\ActorContext;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\View;

final class InventoryReportService extends BaseService
{
    public function __construct(
        private readonly ActorContextProvider $actors,
        private readonly SuperAdminCooperativeAssignmentReader $assignments,
    ) {}

    public function pdf(string $cooperativeId, string $actorId): string
    {
        $actor = $this->actors->forUserId($actorId);
        $simplified = $this->authorize($actor, $cooperativeId);

        $stocks = InventoryStock::query()
            ->where('cooperative_id', $cooperativeId)
            ->orderBy('stockable_type')
            ->orderBy('stockable_id')
            ->get()
            ->map(function (InventoryStock $stock) use ($simplified): array {
                $available = $stock->active_count_session_id !== null ? 0 : max(
                    0,
                    $this->milli($stock->quantity_on_hand)
                    - $this->milli($stock->quantity_reserved_normal)
                    - $this->milli($stock->quantity_quarantined)
                    + $this->milli($stock->quantity_quarantine_authorized)
                    - $this->milli($stock->quantity_reserved_quarantine),
                );
                $threshold = InventoryStockThreshold::query()->where('stock_id', $stock->getKey())->value('low_stock_threshold');

                $row = [
                    'stockable_type' => $stock->stockable_type,
                    'stockable_id' => $stock->stockable_id,
                    'unit_code' => $stock->unit_code,
                    'status' => $available > 0 ? 'En stock' : 'Rupture de stock',
                ];

                if (! $simplified) {
                    $row += [
                        'quantity_on_hand' => (string) $stock->quantity_on_hand,
                        'quantity_reserved' => (string) $stock->quantity_reserved,
                        'quantity_quarantined' => (string) $stock->quantity_quarantined,
                        'quantity_quarantine_authorized' => (string) $stock->quantity_quarantine_authorized,
                        'available_quantity' => $this->format($available),
                        'low_stock_threshold' => $threshold === null ? '—' : (string) $threshold,
                    ];
                }

                return $row;
            })->all();

        $data = [
            'cooperativeId' => $cooperativeId,
            'generatedAt' => now(),
            'simplified' => $simplified,
            'stocks' => $stocks,
            'movements' => [],
            'alerts' => [],
            'countSessions' => [],
            'quarantines' => [],
        ];

        if (! $simplified) {
            $data['movements'] = InventoryMovement::query()
                ->where('cooperative_id', $cooperativeId)
                ->latest('occurred_at')
                ->limit(5000)
                ->get();
            $stockIds = InventoryStock::query()->where('cooperative_id', $cooperativeId)->pluck('id');
            $data['alerts'] = InventoryAlertEpisode::query()
                ->whereIn('stock_id', $stockIds)
                ->latest('triggered_at')
                ->limit(1000)
                ->get();
            $data['countSessions'] = InventoryCountSession::query()
                ->with(['items' => static fn ($query) => $query->orderBy('created_at')])
                ->where('cooperative_id', $cooperativeId)
                ->latest('started_at')
                ->limit(100)
                ->get();
            $data['quarantines'] = InventoryQuarantine::query()
                ->where('cooperative_id', $cooperativeId)
                ->latest('received_at')
                ->limit(1000)
                ->get();
        }

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(View::make('inventory.reports.pdf', $data)->render(), 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return $dompdf->output();
    }

    private function authorize(ActorContext $actor, string $cooperativeId): bool
    {
        if (! $actor->authorized) {
            throw new AuthorizationException('The actor is not authorized to access Inventory reports.');
        }

        if ($actor->hasRole('user')) {
            if (! $actor->hasPermission('inventory.reports.view') || $actor->cooperativeId !== $cooperativeId) {
                throw new AuthorizationException('The actor cannot access this cooperative Inventory report.');
            }

            return true;
        }

        if (! $actor->hasPermission('inventory.reports.export')) {
            throw new AuthorizationException('The actor cannot export Inventory reports.');
        }

        if ($actor->hasRole('super_admin_root')) {
            return false;
        }
        if ($actor->hasRole('cooperative_admin') && $actor->cooperativeId === $cooperativeId) {
            return false;
        }
        if ($actor->hasRole('super_admin') && $this->assignments->isAssigned($actor->id, $cooperativeId)) {
            return false;
        }

        throw new AuthorizationException('The actor is outside the cooperative scope.');
    }

    private function milli(string|int|float $value): int
    {
        $formatted = (string) $value;
        [$whole, $fraction] = array_pad(explode('.', $formatted, 2), 2, '0');
        return ((int) $whole * 1000) + (int) str_pad($fraction, 3, '0', STR_PAD_LEFT);
    }

    private function format(int $value): string
    {
        return intdiv($value, 1000).'.'.str_pad((string) ($value % 1000), 3, '0', STR_PAD_LEFT);
    }
}
