<?php

declare(strict_types=1);

namespace App\Domains\Orders\Models;

use App\Core\Foundation\Models\BaseModel;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Orders\Enums\CooperativeOrderStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class CooperativeOrder extends BaseModel
{
    use SoftDeletes;

    protected $table = 'cooperative_orders';

    protected $fillable = [
        'order_id', 'cooperative_id', 'reference', 'status', 'items_subtotal',
        'delivery_fee', 'grand_total', 'response_deadline_at', 'response_overdue_at',
        'responded_at', 'substituted_by_admin_id', 'substitution_reason',
        'confirmed_at', 'refused_at', 'refusal_reason', 'safety_cancelled_at', 'safety_cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => CooperativeOrderStatus::class,
            'items_subtotal' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'response_deadline_at' => 'datetime',
            'response_overdue_at' => 'datetime',
            'responded_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'safety_cancelled_at' => 'datetime',
            'refused_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function cooperative(): BelongsTo
    {
        return $this->belongsTo(Cooperative::class, 'cooperative_id');
    }

    public function substitutedByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'substituted_by_admin_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'cooperative_order_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class, 'subject_id')
            ->where('subject_type', 'cooperative_order')
            ->orderBy('occurred_at');
    }
}