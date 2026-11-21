<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Http\Controllers;

use App\Domains\Inventory\Services\Reports\InventoryReportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class InventoryReportController
{
    public function __invoke(
        Request $request,
        string $cooperativeId,
        InventoryReportService $reports,
    ): Response {
        $pdf = $reports->pdf($cooperativeId, (string) $request->user()->getKey());

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="mahline-inventory-'.$cooperativeId.'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
