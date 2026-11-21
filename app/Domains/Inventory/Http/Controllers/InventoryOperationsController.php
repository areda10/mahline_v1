<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Http\Controllers;

use App\Domains\Inventory\Contracts\InventoryCountManager;
use App\Domains\Inventory\Contracts\InventoryQuarantineManager;
use App\Domains\Inventory\Contracts\InventoryStockManager;
use App\Domains\Inventory\Contracts\InventoryThresholdManager;
use App\Domains\Inventory\Enums\InventoryInspectionResult;
use App\Domains\Inventory\Enums\InventoryStockableType;
use App\Domains\Inventory\Services\Stock\InventoryStockReadService;
use App\Domains\Inventory\Services\Counts\InventoryCountQueryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

final class InventoryOperationsController
{
    public function index(Request $request, string $cooperativeId, InventoryStockReadService $stocks): Response
    {
        return response()->json([
            'stocks' => $stocks->availability($cooperativeId, (string) $request->user()->getKey()),
        ]);
    }

    public function initialize(Request $request, string $cooperativeId, InventoryStockManager $stocks): Response
    {
        $data = $request->validate([
            'stockable_type' => ['required', Rule::in(['product', 'variant'])],
            'stockable_id' => ['required', 'string', 'size:26'],
            'unit_code' => ['required', Rule::in(['kg', 'g', 'l', 'ml', 'u'])],
            'quantity' => ['required', 'numeric', 'min:0'],
            'reason' => ['required', 'string', 'max:500'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ]);

        $id = $stocks->initialize(
            $cooperativeId,
            InventoryStockableType::from($data['stockable_type']),
            $data['stockable_id'],
            $data['unit_code'],
            (string) $data['quantity'],
            $data['reason'],
            (string) $request->user()->getKey(),
            $data['idempotency_key'],
        );

        return response()->json(['stock_id' => $id], 201);
    }

    public function receive(Request $request, string $cooperativeId, InventoryStockManager $stocks): Response
    {
        $data = $request->validate([
            'stockable_type' => ['required', Rule::in(['product', 'variant'])],
            'stockable_id' => ['required', 'string', 'size:26'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:500'],
            'movement_type' => ['sometimes', Rule::in(['purchase_receipt', 'production_entry', 'manual_adjustment_in'])],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ]);

        $id = $stocks->receive(
            $cooperativeId,
            InventoryStockableType::from($data['stockable_type']),
            $data['stockable_id'],
            (string) $data['quantity'],
            $data['reason'],
            (string) $request->user()->getKey(),
            $data['idempotency_key'],
            $data['movement_type'] ?? 'purchase_receipt',
        );

        return response()->json(['stock_id' => $id]);
    }

    public function adjust(Request $request, string $cooperativeId, InventoryStockManager $stocks): Response
    {
        $data = $request->validate([
            'stockable_type' => ['required', Rule::in(['product', 'variant'])],
            'stockable_id' => ['required', 'string', 'size:26'],
            'signed_quantity' => ['required', 'string', 'regex:/^-?\d{1,12}(?:\.\d{1,3})?$/'],
            'reason' => ['required', 'string', 'max:500'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ]);

        $id = $stocks->adjust(
            $cooperativeId,
            InventoryStockableType::from($data['stockable_type']),
            $data['stockable_id'],
            $data['signed_quantity'],
            $data['reason'],
            (string) $request->user()->getKey(),
            $data['idempotency_key'],
        );

        return response()->json(['stock_id' => $id]);
    }

    public function threshold(Request $request, string $stockId, InventoryThresholdManager $thresholds): Response
    {
        $data = $request->validate([
            'threshold' => ['required', 'string', 'regex:/^\d{1,12}(?:\.\d{1,3})?$/'],
            'reason' => ['required', 'string', 'max:500'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ]);

        $thresholds->setLowStockThreshold(
            $stockId,
            $data['threshold'],
            $data['reason'],
            (string) $request->user()->getKey(),
            $data['idempotency_key'],
        );

        return response()->noContent();
    }

    public function createCount(Request $request, string $cooperativeId, InventoryCountManager $counts): Response
    {
        $data = $request->validate([
            'stock_ids' => ['required', 'array', 'min:1'],
            'stock_ids.*' => ['required', 'string', 'size:26', 'distinct'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ]);

        $id = $counts->createSession(
            $cooperativeId,
            $data['stock_ids'],
            (string) $request->user()->getKey(),
            $data['idempotency_key'],
        );

        return response()->json(['session_id' => $id], 201);
    }

    public function recordCount(Request $request, string $countItemId, InventoryCountManager $counts): Response
    {
        $data = $request->validate([
            'counted_quantity' => ['required', 'string', 'regex:/^\d{1,12}(?:\.\d{1,3})?$/'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $counts->recordCount(
            $countItemId,
            $data['counted_quantity'],
            $data['reason'] ?? null,
            (string) $request->user()->getKey(),
        );

        return response()->noContent();
    }

    public function validateCount(Request $request, string $sessionId, InventoryCountManager $counts): Response
    {
        $counts->validateSession($sessionId, (string) $request->user()->getKey());

        return response()->noContent();
    }

    public function cancelCount(Request $request, string $sessionId, InventoryCountManager $counts): Response
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $counts->cancelSession($sessionId, $data['reason'], (string) $request->user()->getKey());

        return response()->noContent();
    }

    public function receiveQuarantine(Request $request, string $cooperativeId, InventoryQuarantineManager $quarantine): Response
    {
        $data = $request->validate([
            'stockable_type' => ['required', Rule::in(['product', 'variant'])],
            'stockable_id' => ['required', 'string', 'size:26'],
            'quantity' => ['required', 'string', 'regex:/^\d{1,12}(?:\.\d{1,3})?$/'],
            'source_type' => ['required', 'string', 'max:60'],
            'source_id' => ['required', 'string', 'size:26'],
            'reason' => ['required', 'string', 'max:500'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ]);

        $id = $quarantine->receiveReturn(
            $cooperativeId,
            InventoryStockableType::from($data['stockable_type']),
            $data['stockable_id'],
            $data['quantity'],
            $data['source_type'],
            $data['source_id'],
            $data['reason'],
            (string) $request->user()->getKey(),
            $data['idempotency_key'],
        );

        return response()->json(['quarantine_id' => $id], 201);
    }

    public function inspectQuarantine(Request $request, string $quarantineId, InventoryQuarantineManager $quarantine): Response
    {
        $data = $request->validate([
            'result' => ['required', Rule::in(['compliant', 'non_compliant'])],
            'reason' => ['required', 'string', 'max:500'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ]);

        $quarantine->inspect(
            $quarantineId,
            InventoryInspectionResult::from($data['result']),
            $data['reason'],
            (string) $request->user()->getKey(),
            $data['idempotency_key'],
        );

        return response()->noContent();
    }

    public function authorizeQuarantineSale(Request $request, string $quarantineId, InventoryQuarantineManager $quarantine): Response
    {
        $data = $request->validate([
            'quantity' => ['required', 'string', 'regex:/^\d{1,12}(?:\.\d{1,3})?$/'],
            'reason' => ['required', 'string', 'max:500'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ]);

        $id = $quarantine->authorizeSale(
            $quarantineId,
            $data['quantity'],
            $data['reason'],
            (string) $request->user()->getKey(),
            $data['idempotency_key'],
        );

        return response()->json(['authorization_id' => $id], 201);
    }

    public function revokeQuarantineSale(Request $request, string $authorizationId, InventoryQuarantineManager $quarantine): Response
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ]);

        $quarantine->revokeSale(
            $authorizationId,
            $data['reason'],
            (string) $request->user()->getKey(),
            $data['idempotency_key'],
        );

        return response()->noContent();
    }
}
