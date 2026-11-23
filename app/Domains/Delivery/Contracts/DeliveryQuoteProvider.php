<?php

declare(strict_types=1);

namespace App\Domains\Delivery\Contracts;

use App\Domains\Delivery\DTOs\DeliveryQuote;

interface DeliveryQuoteProvider
{
    /**
     * @param list<string> $cooperativeIds
     * @return list<DeliveryQuote> One quote per distinct cooperative shipment.
     */
    public function quoteForCooperatives(array $cooperativeIds): array;
}
