<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Http\Controllers;

use App\Domains\Inventory\Contracts\InventorySecurityReportManager;
use App\Domains\Inventory\Enums\InventorySecurityReportUrgency;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

final class InventorySecurityReportController
{
    public function store(
        Request $request,
        string $cooperativeId,
        InventorySecurityReportManager $reports,
    ): Response {
        $validated = $request->validate([
            'cooperative_order_id' => ['nullable', 'string', 'size:26'],
            'category' => ['required', 'string', 'max:60'],
            'description' => ['required', 'string', 'max:10000'],
            'urgency' => ['required', Rule::in(['normal', 'urgent'])],
            'attachments' => ['sometimes', 'array'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $id = $reports->reportRisk(
            $cooperativeId,
            $validated['cooperative_order_id'] ?? null,
            $validated['category'],
            $validated['description'],
            InventorySecurityReportUrgency::from($validated['urgency']),
            (string) $request->user()->getKey(),
            $request->file('attachments', []),
        );

        return response()->json([
            'report_id' => $id,
            'status' => 'open',
            'message' => 'Le signalement a été enregistré.',
        ], 201);
    }

    public function show(
        Request $request,
        string $reportId,
        InventorySecurityReportManager $reports,
    ): Response {
        $report = $reports->visibleReport($reportId, (string) $request->user()->getKey());

        return response()->json([
            'report' => $report,
        ]);
    }

    public function close(
        Request $request,
        string $reportId,
        InventorySecurityReportManager $reports,
    ): Response {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $reports->close($reportId, $validated['reason'], (string) $request->user()->getKey());

        return response()->noContent();
    }

    public function reopen(
        Request $request,
        string $reportId,
        InventorySecurityReportManager $reports,
    ): Response {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $reports->reopen($reportId, $validated['reason'], (string) $request->user()->getKey());

        return response()->noContent();
    }
}
