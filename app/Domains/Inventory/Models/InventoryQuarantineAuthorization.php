<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Models;

use App\Core\Foundation\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class InventoryQuarantineAuthorization extends BaseModel
{
    protected $table = 'inventory_quarantine_authorizations';

    protected $fillable = [
        'quarantine_id',
        'stock_id',
        'quantity_authorized',
        'quantity_remaining',
        'quantity_reserved',
        'status',
        'authorized_by',
        'reason',
        'authorized_at',
        'revoked_by',
        'revoked_at',
        'revoke_reason',
    ];

    protected function casts(): array
    {
        return [
            'quantity_authorized' => 'decimal:3',
            'quantity_remaining' => 'decimal:3',
            'quantity_reserved' => 'decimal:3',
            'authorized_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(static function (): never {
            throw new LogicException('Inventory historical records cannot be deleted.');
        });
    }

    public function quarantine(): BelongsTo
    {
        return $this->belongsTo(InventoryQuarantine::class, 'quarantine_id');
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(InventoryStock::class, 'stock_id');
    }
}
