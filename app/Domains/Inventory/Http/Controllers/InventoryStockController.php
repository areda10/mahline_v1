<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Http\Controllers;

use App\Domains\Inventory\Contracts\InventoryStockManager;
use App\Domains\Inventory\Enums\InventoryStockableType;
use App\Domains\Inventory\Services\Stock\InventoryStockQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class InventoryStockController
{
    public function index(Request $request, InventoryStockQueryService $stocks, ?string $cooperativeId = null): JsonResponse
    {
        return response()->json([
            'data' => $stocks->index((string) $request->user()->getKey(), $cooperativeId),
        ]);
    }

    public function show(Request $request, string $stockId, InventoryStockQueryService $stocks): JsonResponse
    {
        return response()->json([
            'data' => $stocks->show($stockId, (string) $request->user()->getKey()),
        ]);
    }

    public function initialize(Request $request, string $cooperativeId, InventoryStockManager $stocks): JsonResponse
    {
        $data = $request->validate([
            'stockable_type' => ['required', 'in:product,variant'],
            'stockable_id' => ['required', 'ulid'],
            'unit_code' => ['required', 'in:kg,g,l,ml,u'],
            'quantity' => ['required', 'string', 'regex:/^\d{1,12}(?:\.\d{1,3})?$/'],
            'reason' => ['required', 'string', 'max:500'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ]);

        $stockId = $stocks->initialize(
            $cooperativeId,
            InventoryStockableType::from($data['stockable_type']),
            $data['stockable_id'],
            $data['unit_code'],
            $data['quantity'],
            $data['reason'],
            (string) $request->user()->getKey(),
            $this->idempotencyKey($request, $data['idempotency_key']),
        );

        return response()->json(['stock_id' => $stockId], 201);
    }

    public function receive(Request $request, string $cooperativeId, InventoryStockManager $stocks): JsonResponse
    {
        $data = $request->validate([
            'stockable_type' => ['required', 'in:product,variant'],
            'stockable_id' => ['required', 'ulid'],
            'quantity' => ['required', 'string', 'regex:/^\d{1,12}(?:\.\d{1,3})?$/'],
            'reason' => ['required', 'string', 'max:500'],
            'movement_type' => ['sometimes', 'in:purchase_receipt,production_entry,manual_adjustment_in'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ]);

        $stockId = $stocks->receive(
            $cooperativeId,
            InventoryStockableType::from($data['stockable_type']),
            $data['stockable_id'],
            $data['quantity'],
            $data['reason'],
            (string) $request->user()->getKey(),
            $this->idempotencyKey($request, $data['idempotency_key']),
            $data['movement_type'] ?? 'purchase_receipt',
        );

        return response()->json(['stock_id' => $stockId], 200);
    }

    public function adjust(Request $request, string $cooperativeId, InventoryStockManager $stocks): JsonResponse
    {
        $data = $request->validate([
            'stockable_type' => ['required', 'in:product,variant'],
            'stockable_id' => ['required', 'ulid'],
            'signed_quantity' => ['required', 'string', 'regex:/^-?\d{1,12}(?:\.\d{1,3})?$/'],
            'reason' => ['required', 'string', 'max:500'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ]);

        $stockId = $stocks->adjust(
            $cooperativeId,
            InventoryStockableType::from($data['stockable_type']),
            $data['stockable_id'],
            $data['signed_quantity'],
            $data['reason'],
            (string) $request->user()->getKey(),
            $this->idempotencyKey($request, $data['idempotency_key']),
        );

        return response()->json(['stock_id' => $stockId], 200);
    }

    private function idempotencyKey(Request $request, string $validatedKey): string
    {
        $header = trim((string) $request->header('Idempotency-Key'));

        return $header !== '' ? $header : $validatedKey;
    }
}
