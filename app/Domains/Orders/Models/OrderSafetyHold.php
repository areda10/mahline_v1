<?php

declare(strict_types=1);

namespace App\Domains\Orders\Models;

use App\Core\Foundation\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OrderSafetyHold extends BaseModel
{
    protected $table = 'order_safety_holds';

    protected $fillable = [
        'cooperative_order_id',
        'cooperative_id',
        'source_type',
        'source_id',
        'reason',
        'status',
        'raised_by_id',
        'raised_by_type',
        'resolved_by_id',
        'resolved_by_type',
        'resolution',
        'resolution_reason',
        'raised_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'raised_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function cooperativeOrder(): BelongsTo
    {
        return $this->belongsTo(CooperativeOrder::class, 'cooperative_order_id');
    }
}
