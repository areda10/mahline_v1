<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Http\Controllers;

use App\Domains\Inventory\Contracts\InventorySecurityReportManager;
use App\Domains\Inventory\Services\Counts\InventoryCountQueryService;
use App\Domains\Inventory\Services\Quarantine\InventoryQuarantineQueryService;
use App\Domains\Inventory\Services\Stock\InventoryStockQueryService;
use App\Shared\Contracts\ActorContextProvider;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class InventoryWorkspaceController
{
    public function __invoke(
        Request $request,
        InventoryStockQueryService $stocks,
        InventoryCountQueryService $counts,
        InventoryQuarantineQueryService $quarantines,
        InventorySecurityReportManager $securityReports,
        ActorContextProvider $actors,
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

        $actor = $actors->forUserId($actorId);
        $isManager = $actor->hasRole('super_admin_root')
            || $actor->hasRole('super_admin')
            || $actor->hasRole('cooperative_admin');
        $canManageQuarantine = $actor->authorized
            && $isManager
            && $actor->hasPermission('inventory.quarantine.manage');
        $quarantineRows = $canManageQuarantine
            ? $cooperativeIds
                ->flatMap(fn (string $cooperativeId): array => $quarantines->index($cooperativeId, $actorId))
                ->values()
                ->all()
            : [];
        $visibleSecurityReports = $securityReports->visibleReports($actorId);

        return view('backoffice.inventory.index', [
            'stocks' => $stockRows,
            'stocksByCooperative' => collect($stockRows)->groupBy('cooperative_id'),
            'cooperativeIds' => $cooperativeIds->all(),
            'countSessions' => $sessions,
            'canCreateCount' => $actor->authorized && $isManager && $actor->hasPermission('inventory.count.create'),
            'canManageStock' => $actor->authorized && $isManager
                && $actor->hasPermission('inventory.stock.receive')
                && $actor->hasPermission('inventory.stock.adjust'),
            'canManageThreshold' => $actor->authorized && $isManager && $actor->hasPermission('inventory.threshold.manage'),
            'canInitializeStock' => $actor->authorized && $isManager && $actor->hasPermission('inventory.stock.initialize'),
            'canManageQuarantine' => $canManageQuarantine,
            'quarantines' => $quarantineRows,
            'securityReports' => $visibleSecurityReports,
            'canCreateSecurityReport' => $actor->authorized && $actor->hasPermission('inventory.security_reports.create'),
            'canManageSecurityReports' => $actor->authorized && $isManager && $actor->hasPermission('inventory.security_reports.manage'),
            'defaultCooperativeId' => (string) ($actor->cooperativeId ?? ''),
        ]);
    }
}
