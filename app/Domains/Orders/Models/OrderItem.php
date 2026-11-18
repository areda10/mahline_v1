<?php

declare(strict_types=1);

namespace App\Domains\Orders\Models;

use App\Core\Foundation\Models\BaseModel;
use App\Domains\Orders\Enums\OrderItemStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class OrderItem extends BaseModel
{
    use SoftDeletes;

    protected $table = 'order_items';

    protected $fillable = [
        'cooperative_order_id', 'product_id', 'product_variant_id', 'product_snapshot',
        'quantity', 'unit', 'unit_price', 'tax_rate', 'tax_amount',
        'line_subtotal', 'line_total', 'status', 'cancelled_at', 'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'product_snapshot' => 'array',
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'tax_rate' => 'decimal:3',
            'tax_amount' => 'decimal:2',
            'line_subtotal' => 'decimal:2',
            'line_total' => 'decimal:2',
            'status' => OrderItemStatus::class,
            'cancelled_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function cooperativeOrder(): BelongsTo
    {
        return $this->belongsTo(CooperativeOrder::class, 'cooperative_order_id');
    }

    public function changeProposals(): HasMany
    {
        return $this->hasMany(OrderChangeProposal::class, 'order_item_id');
    }
}