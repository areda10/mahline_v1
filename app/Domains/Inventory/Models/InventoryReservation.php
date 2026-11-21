<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Models;

use App\Core\Foundation\Models\BaseModel;
use LogicException;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class InventoryReservation extends BaseModel
{
    protected $table = 'inventory_reservations';

    protected $fillable = [
        'cooperative_order_id',
        'cooperative_id',
        'status',
        'idempotency_key',
        'actor_id',
        'actor_type',
        'reserved_at',
        'expires_at',
        'consumed_at',
        'released_at',
        'release_reason',
    ];

    protected function casts(): array
    {
        return [
            'reserved_at' => 'datetime',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(static function (): never {
            throw new LogicException('Inventory historical records cannot be deleted.');
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryReservationItem::class, 'reservation_id');
    }
}
