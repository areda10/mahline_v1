<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Http\Controllers;

use App\Domains\Inventory\Services\Counts\InventoryCountQueryService;
use App\Domains\Inventory\Services\Stock\InventoryStockQueryService;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class InventoryWorkspaceController
{
    public function __invoke(
        Request $request,
        InventoryStockQueryService $stocks,
        InventoryCountQueryService $counts,
    ): View {
        $actorId = (string) $request->user()->getKey();
        $stockRows = $stocks->index($actorId);
        $cooperativeIds = collect($stockRows)
            ->pluck('cooperative_id')
            ->unique()
            ->values();

        $sessions = $cooperativeIds
            ->flatMap(fn (string $cooperativeId): array => $counts->sessionsForCooperative($cooperativeId, $actorId))
            ->sortByDesc('started_at')
            ->values()
            ->all();

        return view('backoffice.inventory.index', [
            'stocks' => $stockRows,
            'cooperativeIds' => $cooperativeIds->all(),
            'countSessions' => $sessions,
        ]);
    }
}
