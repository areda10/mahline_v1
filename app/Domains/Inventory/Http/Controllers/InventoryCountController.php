<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Http\Controllers;

use App\Domains\Inventory\Contracts\InventoryCountManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class InventoryCountController
{
    public function create(Request $request, string $cooperativeId, InventoryCountManager $counts): JsonResponse
    {
        $data = $request->validate([
            'stock_ids' => ['required', 'array', 'min:1'],
            'stock_ids.*' => ['required', 'ulid', 'distinct'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ]);

        $sessionId = $counts->createSession(
            $cooperativeId,
            $data['stock_ids'],
            (string) $request->user()->getKey(),
            trim((string) $request->header('Idempotency-Key', $data['idempotency_key'])),
        );

        return response()->json(['session_id' => $sessionId], 201);
    }

    public function record(Request $request, string $sessionId, string $itemId, InventoryCountManager $counts): JsonResponse
    {
        $data = $request->validate([
            'counted_quantity' => ['required', 'string', 'regex:/^\d{1,12}(?:\.\d{1,3})?$/'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $counts->recordCount(
            $itemId,
            $data['counted_quantity'],
            $data['reason'] ?? null,
            (string) $request->user()->getKey(),
        );

        return response()->json(['status' => 'counted']);
    }

    public function validateSession(Request $request, string $sessionId, InventoryCountManager $counts): JsonResponse
    {
        $counts->validateSession($sessionId, (string) $request->user()->getKey());

        return response()->json(['status' => 'validated_or_partially_validated']);
    }

    public function cancel(Request $request, string $sessionId, InventoryCountManager $counts): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $counts->cancelSession($sessionId, $data['reason'], (string) $request->user()->getKey());

        return response()->json(['status' => 'cancelled']);
    }
}
