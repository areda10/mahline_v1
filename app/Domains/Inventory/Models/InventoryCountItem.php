<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Models;

use App\Core\Foundation\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class InventoryCountItem extends BaseModel
{
    protected $table = 'inventory_count_items';

    protected $fillable = [
        'session_id',
        'stock_id',
        'expected_quantity',
        'counted_quantity',
        'difference_quantity',
        'difference_reason',
        'status',
        'counted_by',
        'counted_at',
        'validated_by',
        'validated_at',
    ];

    protected function casts(): array
    {
        return [
            'expected_quantity' => 'decimal:3',
            'counted_quantity' => 'decimal:3',
            'difference_quantity' => 'decimal:3',
            'counted_at' => 'datetime',
            'validated_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(InventoryCountSession::class, 'session_id');
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(InventoryStock::class, 'stock_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(InventoryCountItemEvent::class, 'count_item_id')->orderBy('occurred_at');
    }
}
