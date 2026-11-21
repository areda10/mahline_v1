<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Models;

use App\Core\Foundation\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class InventoryAlertEpisode extends BaseModel
{
    protected $table = 'inventory_alert_episodes';

    protected $fillable = [
        'stock_id', 'alert_type', 'available_quantity_at_trigger',
        'threshold_at_trigger', 'triggered_at', 'cleared_at',
    ];

    protected function casts(): array
    {
        return [
            'available_quantity_at_trigger' => 'decimal:3',
            'threshold_at_trigger' => 'decimal:3',
            'triggered_at' => 'datetime',
            'cleared_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(static function (): never {
            throw new LogicException('Inventory historical records cannot be deleted.');
        });
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(InventoryStock::class, 'stock_id');
    }
}
