<?php

declare(strict_types=1);

namespace App\Domains\Delivery\Services;

use App\Domains\Delivery\Contracts\DeliveryQuoteProvider;
use App\Domains\Delivery\DTOs\DeliveryQuote;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class InitialFlatRateDeliveryQuoteProvider implements DeliveryQuoteProvider
{
    public function quoteForCooperatives(array $cooperativeIds): array
    {
        $ids = array_values(array_unique(array_map('strval', $cooperativeIds)));
        if ($ids === [] || in_array('', $ids, true)) {
            throw ValidationException::withMessages([
                'delivery' => ['Une expédition par coopérative commandée est obligatoire.'],
            ]);
        }

        $amount = (string) config('delivery.initial_fee_per_cooperative', '40.00');
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $amount) || bccomp($amount, '0.00', 2) <= 0) {
            throw ValidationException::withMessages([
                'delivery' => ['Le tarif initial de livraison doit être strictement positif.'],
            ]);
        }

        $issuedAt = Carbon::now();
        $expiresAt = $issuedAt->copy()->addMinutes((int) config('delivery.quote_ttl_minutes', 30));

        return array_map(
            static fn (string $cooperativeId): DeliveryQuote => new DeliveryQuote(
                cooperativeId: $cooperativeId,
                reference: (string) Str::ulid(),
                amount: number_format((float) $amount, 2, '.', ''),
                currency: 'MAD',
                tariffCode: 'INITIAL_FLAT_PER_COOPERATIVE',
                issuedAt: $issuedAt->copy(),
                expiresAt: $expiresAt->copy(),
            ),
            $ids,
        );
    }
}
