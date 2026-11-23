<?php

declare(strict_types=1);

return [
    // Temporary V1 tariff: one delivery shipment per cooperative suborder.
    // Replace the provider with carrier-backed rate selection when Delivery rates are implemented.
    'initial_fee_per_cooperative' => env('MAHLINE_INITIAL_DELIVERY_FEE_PER_COOPERATIVE', '40.00'),
    'quote_ttl_minutes' => (int) env('MAHLINE_DELIVERY_QUOTE_TTL_MINUTES', 30),
];
