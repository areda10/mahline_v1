<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Http\Controllers;

use App\Domains\Inventory\Services\Counts\InventoryCountQueryService;
use App\Domains\Inventory\Services\Quarantine\InventoryQuarantineQueryService;
use App\Domains\Inventory\Contracts\InventorySecurityReportManager;
use App\Domains\Inventory\Services\Stock\InventoryStockQueryService;
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
        $canManageQuarantine = $user->hasAnyRole(['super_admin_root', 'super_admin', 'cooperative_admin'])
            && $user->hasPermission('inventory.quarantine.manage');
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
            'canCreateCount' => $user->hasAnyRole(['super_admin_root', 'super_admin', 'cooperative_admin'])
                && $user->hasPermission('inventory.count.create'),
            'canManageStock' => $user->hasAnyRole(['super_admin_root', 'super_admin', 'cooperative_admin'])
                && $user->hasPermission('inventory.stock.receive')
                && $user->hasPermission('inventory.stock.adjust'),
            'canManageThreshold' => $user->hasAnyRole(['super_admin_root', 'super_admin', 'cooperative_admin'])
                && $user->hasPermission('inventory.threshold.manage'),
            'canInitializeStock' => $user->hasAnyRole(['super_admin_root', 'super_admin', 'cooperative_admin'])
                && $user->hasPermission('inventory.stock.initialize'),
            'canManageQuarantine' => $canManageQuarantine,
            'quarantines' => $quarantineRows,
            'securityReports' => $visibleSecurityReports,
            'canCreateSecurityReport' => $user->hasPermission('inventory.security_reports.create'),
            'canManageSecurityReports' => $user->hasPermission('inventory.security_reports.manage'),
            'defaultCooperativeId' => (string) ($user->cooperative_id ?? ''),
        ]);
    }
}
