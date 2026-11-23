<?php

declare(strict_types=1);

namespace App\Domains\Delivery\DTOs;

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final readonly class DeliveryQuote
{
    public function __construct(
        public string $cooperativeId,
        public string $reference,
        public string $amount,
        public string $currency,
        public string $tariffCode,
        public Carbon $issuedAt,
        public Carbon $expiresAt,
    ) {}

    public function assertUsable(): void
    {
        if (bccomp($this->amount, '0.00', 2) <= 0 || $this->currency !== 'MAD') {
            throw ValidationException::withMessages([
                'delivery' => ['Le devis de livraison est invalide ou son montant est nul.'],
            ]);
        }

        if ($this->expiresAt->lessThanOrEqualTo(now())) {
            throw ValidationException::withMessages([
                'delivery' => ['Le devis de livraison a expiré. Veuillez actualiser le panier.'],
            ]);
        }
    }

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        return [
            'quote_reference' => $this->reference,
            'cooperative_id' => $this->cooperativeId,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'tariff_code' => $this->tariffCode,
            'issued_at' => $this->issuedAt->toIso8601String(),
            'expires_at' => $this->expiresAt->toIso8601String(),
        ];
    }
}
