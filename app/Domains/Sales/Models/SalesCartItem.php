<?php

declare(strict_types=1);

namespace App\Domains\Sales\Models;

use App\Core\Foundation\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SalesCartItem extends BaseModel
{
    protected $table = 'sales_cart_items';

    protected $fillable = [
        'cart_id',
        'product_id',
        'product_variant_id',
        'cooperative_id',
        'unit_code',
        'quantity',
        'initial_product_snapshot',
        'current_product_snapshot',
        'initial_unit_price',
        'current_unit_price',
        'accepted_unit_price',
        'accepted_price_fingerprint',
        'commercial_fingerprint',
        'line_fingerprint',
        'status',
        'unavailable_reason',
        'removed_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'initial_product_snapshot' => 'array',
            'current_product_snapshot' => 'array',
            'initial_unit_price' => 'decimal:2',
            'current_unit_price' => 'decimal:2',
            'accepted_unit_price' => 'decimal:2',
            'removed_at' => 'datetime',
        ];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(SalesCart::class, 'cart_id');
    }
}
