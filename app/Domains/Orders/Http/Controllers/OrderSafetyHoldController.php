<?php

declare(strict_types=1);

namespace App\Domains\Orders\Http\Controllers;

use App\Shared\Contracts\OrderSafetyHoldManager;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

final class OrderSafetyHoldController
{
    public function resolve(
        Request $request,
        string $holdId,
        OrderSafetyHoldManager $safetyHolds,
    ): Response {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['resume', 'cancel'])],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $safetyHolds->resolveHold(
            $holdId,
            $data['decision'],
            $data['reason'],
            (string) $request->user()->getKey(),
        );

        return response()->noContent();
    }
}
