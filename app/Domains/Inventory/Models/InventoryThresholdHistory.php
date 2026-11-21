<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Models;

use App\Core\Foundation\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class InventoryThresholdHistory extends BaseModel
{
    protected $table = 'inventory_threshold_histories';

    protected $fillable = ['stock_id', 'old_threshold', 'new_threshold', 'actor_id', 'reason', 'occurred_at'];

    protected function casts(): array
    {
        return [
            'old_threshold' => 'decimal:3',
            'new_threshold' => 'decimal:3',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Inventory threshold history is immutable.');
        });
        static::deleting(static function (): never {
            throw new LogicException('Inventory threshold history cannot be deleted.');
        });
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(InventoryStock::class, 'stock_id');
    }
}
