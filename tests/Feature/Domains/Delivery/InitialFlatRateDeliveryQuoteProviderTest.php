<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Delivery;

use App\Domains\Delivery\DTOs\DeliveryQuote;
use App\Domains\Delivery\Services\InitialFlatRateDeliveryQuoteProvider;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class InitialFlatRateDeliveryQuoteProviderTest extends TestCase
{
    public function test_it_quotes_one_forty_mad_shipment_per_distinct_cooperative(): void
    {
        config()->set('delivery.initial_fee_per_cooperative', '40.00');

        $quotes = app(InitialFlatRateDeliveryQuoteProvider::class)
            ->quoteForCooperatives(['coop-a', 'coop-a', 'coop-b', 'coop-c']);

        $this->assertCount(3, $quotes);
        $this->assertSame(['coop-a', 'coop-b', 'coop-c'], array_map(
            static fn ($quote): string => $quote->cooperativeId,
            $quotes,
        ));
        $this->assertSame(['40.00', '40.00', '40.00'], array_map(
            static fn ($quote): string => $quote->amount,
            $quotes,
        ));
        $this->assertSame('120.00', number_format(array_sum(array_map(
            static fn ($quote): float => (float) $quote->amount,
            $quotes,
        )), 2, '.', ''));
    }

    public function test_it_rejects_a_zero_initial_tariff(): void
    {
        config()->set('delivery.initial_fee_per_cooperative', '0.00');

        $this->expectException(ValidationException::class);

        app(InitialFlatRateDeliveryQuoteProvider::class)->quoteForCooperatives(['coop-a']);
    }

    public function test_quote_is_rejected_after_expiration(): void
    {
        $quote = app(InitialFlatRateDeliveryQuoteProvider::class)
            ->quoteForCooperatives(['coop-a'])[0];

        $expired = new DeliveryQuote(
            cooperativeId: $quote->cooperativeId,
            reference: $quote->reference,
            amount: $quote->amount,
            currency: $quote->currency,
            tariffCode: $quote->tariffCode,
            issuedAt: now()->subHour(),
            expiresAt: now()->subMinute(),
        );

        $this->expectException(ValidationException::class);
        $expired->assertUsable();
    }
}
