<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Http\Controllers;

use App\Domains\Inventory\Contracts\InventoryThresholdManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class InventoryThresholdController
{
    public function update(Request $request, string $stockId, InventoryThresholdManager $thresholds): JsonResponse
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
            trim((string) $request->header('Idempotency-Key', $data['idempotency_key'])),
        );

        return response()->json(['status' => 'updated']);
    }
}
