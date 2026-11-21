<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Http\Controllers;

use App\Domains\Inventory\Contracts\InventoryQuarantineManager;
use App\Domains\Inventory\Enums\InventoryInspectionResult;
use App\Domains\Inventory\Enums\InventoryStockableType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class InventoryQuarantineController
{
    public function receive(Request $request, string $cooperativeId, InventoryQuarantineManager $quarantines): JsonResponse
    {
        $data = $request->validate([
            'stockable_type' => ['required', 'in:product,variant'],
            'stockable_id' => ['required', 'ulid'],
            'quantity' => ['required', 'string', 'regex:/^\d{1,12}(?:\.\d{1,3})?$/'],
            'source_type' => ['required', 'string', 'max:60'],
            'source_id' => ['required', 'ulid'],
            'reason' => ['required', 'string', 'max:500'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ]);

        $quarantineId = $quarantines->receiveReturn(
            $cooperativeId,
            InventoryStockableType::from($data['stockable_type']),
            $data['stockable_id'],
            $data['quantity'],
            $data['source_type'],
            $data['source_id'],
            $data['reason'],
            (string) $request->user()->getKey(),
            trim((string) $request->header('Idempotency-Key', $data['idempotency_key'])),
        );

        return response()->json(['quarantine_id' => $quarantineId], 201);
    }

    public function inspect(Request $request, string $quarantineId, InventoryQuarantineManager $quarantines): JsonResponse
    {
        $data = $request->validate([
            'result' => ['required', 'in:compliant,non_compliant'],
            'reason' => ['required', 'string', 'max:500'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ]);

        $quarantines->inspect(
            $quarantineId,
            InventoryInspectionResult::from($data['result']),
            $data['reason'],
            (string) $request->user()->getKey(),
            trim((string) $request->header('Idempotency-Key', $data['idempotency_key'])),
        );

        return response()->json(['status' => 'inspected']);
    }

    public function authorizeSale(Request $request, string $quarantineId, InventoryQuarantineManager $quarantines): JsonResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'string', 'regex:/^\d{1,12}(?:\.\d{1,3})?$/'],
            'reason' => ['required', 'string', 'max:500'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ]);

        $authorizationId = $quarantines->authorizeSale(
            $quarantineId,
            $data['quantity'],
            $data['reason'],
            (string) $request->user()->getKey(),
            trim((string) $request->header('Idempotency-Key', $data['idempotency_key'])),
        );

        return response()->json(['authorization_id' => $authorizationId], 201);
    }

    public function revokeSale(Request $request, string $authorizationId, InventoryQuarantineManager $quarantines): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ]);

        $quarantines->revokeSale(
            $authorizationId,
            $data['reason'],
            (string) $request->user()->getKey(),
            trim((string) $request->header('Idempotency-Key', $data['idempotency_key'])),
        );

        return response()->json(['status' => 'revoked']);
    }
}
