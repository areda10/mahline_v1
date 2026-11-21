<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Models;

use App\Core\Foundation\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class InventoryQuarantine extends BaseModel
{
    protected $table = 'inventory_quarantines';

    protected $fillable = [
        'stock_id',
        'cooperative_id',
        'source_type',
        'source_id',
        'quantity_received',
        'quantity_remaining',
        'quantity_authorized',
        'status',
        'inspection_result',
        'reason',
        'inspection_reason',
        'received_by',
        'inspected_by',
        'received_at',
        'inspected_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity_received' => 'decimal:3',
            'quantity_remaining' => 'decimal:3',
            'quantity_authorized' => 'decimal:3',
            'received_at' => 'datetime',
            'inspected_at' => 'datetime',
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

    public function authorizations(): HasMany
    {
        return $this->hasMany(InventoryQuarantineAuthorization::class, 'quarantine_id');
    }
}
