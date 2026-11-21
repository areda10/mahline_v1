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

        $user = $request->user();

        return view('backoffice.inventory.index', [
            'stocks' => $stockRows,
            'stocksByCooperative' => collect($stockRows)->groupBy('cooperative_id'),
            'cooperativeIds' => $cooperativeIds->all(),
            'countSessions' => $sessions,
            'canCreateCount' => $user->hasAnyRole(['super_admin_root', 'super_admin', 'cooperative_admin'])
                && $user->hasPermission('inventory.count.create'),
            'canManageStock' => $user->hasAnyRole(['super_admin_root', 'super_admin', 'cooperative_admin'])
                && $user->hasPermission('inventory.stock.receive')
                && $user->hasPermission('inventory.stock.adjust'),
            'canInitializeStock' => $user->hasAnyRole(['super_admin_root', 'super_admin', 'cooperative_admin'])
                && $user->hasPermission('inventory.stock.initialize'),
        ]);
    }
}
