<?php

declare(strict_types=1);

namespace App\Domains\Orders\Models;

use App\Core\Foundation\Models\BaseModel;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Orders\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Order extends BaseModel
{
    use SoftDeletes;

    protected $table = 'orders';

    protected $fillable = [
        'customer_id', 'reference', 'status', 'currency', 'items_subtotal',
        'delivery_total', 'grand_total', 'delivery_address_snapshot', 'delivery_snapshot',
        'placed_at', 'confirmed_at', 'cancelled_at', 'cancellation_reason',
        'checkout_idempotency_key', 'checkout_request_hash', 'inventory_excluded_cooperative_ids',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'delivery_address_snapshot' => 'array',
            'delivery_snapshot' => 'array',
            'inventory_excluded_cooperative_ids' => 'array',
            'items_subtotal' => 'decimal:2',
            'delivery_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'placed_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function cooperativeOrders(): HasMany
    {
        return $this->hasMany(CooperativeOrder::class, 'order_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class, 'subject_id')
            ->where('subject_type', 'order')
            ->orderBy('occurred_at');
    }
}
