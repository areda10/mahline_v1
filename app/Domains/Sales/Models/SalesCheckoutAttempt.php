<?php

declare(strict_types=1);

namespace App\Domains\Sales\Models;

use App\Core\Foundation\Models\BaseModel;

final class SalesCheckoutAttempt extends BaseModel
{
    protected $table = 'sales_checkout_attempts';

    protected $fillable = [
        'customer_id',
        'cart_id',
        'delivery_address_id',
        'idempotency_key_hash',
        'order_id',
        'ordered_cooperative_ids',
        'excluded_cooperative_ids',
        'response_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'ordered_cooperative_ids' => 'array',
            'excluded_cooperative_ids' => 'array',
            'response_snapshot' => 'array',
        ];
    }
}
