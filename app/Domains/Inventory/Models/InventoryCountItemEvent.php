<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Models;

use App\Core\Foundation\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class InventoryCountItemEvent extends BaseModel
{
    protected $table = 'inventory_count_item_events';

    protected $fillable = [
        'count_item_id',
        'event_type',
        'old_quantity',
        'new_quantity',
        'reason',
        'actor_id',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'old_quantity' => 'decimal:3',
            'new_quantity' => 'decimal:3',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        self::updating(static function (): never {
            throw new LogicException('Inventory count item events are immutable.');
        });

        self::deleting(static function (): never {
            throw new LogicException('Inventory count item events cannot be deleted.');
        });
    }

    public function countItem(): BelongsTo
    {
        return $this->belongsTo(InventoryCountItem::class, 'count_item_id');
    }
}
