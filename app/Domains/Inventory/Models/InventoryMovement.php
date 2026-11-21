<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Models;

use App\Core\Foundation\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class InventoryMovement extends BaseModel
{
    protected $table = 'inventory_movements';

    protected $fillable = [
        'stock_id',
        'cooperative_id',
        'movement_type',
        'quantity_delta',
        'reserved_delta',
        'reserved_normal_delta',
        'reserved_quarantine_delta',
        'quarantined_delta',
        'quarantine_authorized_delta',
        'balance_before',
        'balance_after',
        'unit_code',
        'reason',
        'actor_id',
        'actor_type',
        'reference_type',
        'reference_id',
        'idempotency_key',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity_delta' => 'decimal:3',
            'reserved_delta' => 'decimal:3',
            'reserved_normal_delta' => 'decimal:3',
            'reserved_quarantine_delta' => 'decimal:3',
            'quarantined_delta' => 'decimal:3',
            'quarantine_authorized_delta' => 'decimal:3',
            'balance_before' => 'array',
            'balance_after' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        self::updating(static function (): never {
            throw new LogicException('Inventory movements are immutable.');
        });

        self::deleting(static function (): never {
            throw new LogicException('Inventory movements cannot be deleted.');
        });
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(InventoryStock::class, 'stock_id');
    }
}
