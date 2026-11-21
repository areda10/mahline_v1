<?php

declare(strict_types=1);

namespace App\Domains\Orders\Http\Controllers;

use App\Domains\Orders\Services\OrderAvailabilityQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OrderAvailabilityController
{
    public function show(
        Request $request,
        string $orderId,
        OrderAvailabilityQueryService $availability,
    ): JsonResponse {
        return response()->json([
            'data' => $availability->forOrder(
                $orderId,
                (string) $request->user()->getKey(),
            ),
        ]);
    }
}
