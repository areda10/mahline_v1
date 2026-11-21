<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Http\Controllers;

use App\Domains\Inventory\Services\Counts\InventoryCountQueryService;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class InventoryCountWorkspaceController
{
    public function __invoke(
        Request $request,
        string $sessionId,
        InventoryCountQueryService $counts,
    ): View {
        return view('backoffice.inventory.count-sessions.show', [
            'session' => $counts->show(
                $sessionId,
                (string) $request->user()->getKey(),
            ),
        ]);
    }
}
