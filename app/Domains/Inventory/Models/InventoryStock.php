<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Models;

use App\Core\Foundation\Models\BaseModel;
use LogicException;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Domains\Inventory\Services\Alerts\InventoryAlertEvaluator;

final class InventoryStock extends BaseModel
{
    protected $table = 'inventory_stocks';

    protected $fillable = [
        'cooperative_id',
        'stockable_type',
        'stockable_id',
        'unit_code',
        'quantity_on_hand',
        'quantity_reserved',
        'quantity_reserved_normal',
        'quantity_reserved_quarantine',
        'quantity_quarantined',
        'quantity_quarantine_authorized',
        'active_count_session_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'decimal:3',
            'quantity_reserved' => 'decimal:3',
            'quantity_reserved_normal' => 'decimal:3',
            'quantity_reserved_quarantine' => 'decimal:3',
            'quantity_quarantined' => 'decimal:3',
            'quantity_quarantine_authorized' => 'decimal:3',
        ];
    }

    protected static function booted(): void
    {
        static::saved(static function (InventoryStock $stock): void {
            app(InventoryAlertEvaluator::class)->evaluate($stock);
        });

        static::deleting(static function (): never {
            throw new LogicException('Inventory historical records cannot be deleted.');
        });
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'stock_id')
            ->orderBy('occurred_at');
    }

    public function reservationItems(): HasMany
    {
        return $this->hasMany(InventoryReservationItem::class, 'stock_id');
    }
}
