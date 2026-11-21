<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Models;

use App\Core\Foundation\Models\BaseModel;
use LogicException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class InventoryReservationItem extends BaseModel
{
    protected $table = 'inventory_reservation_items';

    protected $fillable = [
        'reservation_id',
        'stock_id',
        'order_item_id',
        'quantity',
        'quantity_normal',
        'quantity_quarantine',
        'unit_code',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'quantity_normal' => 'decimal:3',
            'quantity_quarantine' => 'decimal:3',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(static function (): never {
            throw new LogicException('Inventory historical records cannot be deleted.');
        });
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(InventoryReservation::class, 'reservation_id');
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(InventoryStock::class, 'stock_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(InventoryReservationAllocation::class, 'reservation_item_id');
    }
}
