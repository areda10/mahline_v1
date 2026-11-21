<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Models;

use App\Core\Foundation\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

final class InventoryCountSession extends BaseModel
{
    protected $table = 'inventory_count_sessions';

    protected $fillable = [
        'cooperative_id',
        'status',
        'created_by',
        'validated_by',
        'cancelled_by',
        'started_at',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (InventoryCountSession $session): void {
            if (in_array($session->getOriginal('status'), ['completed', 'cancelled'], true)) {
                throw new LogicException('Closed inventory count sessions are immutable.');
            }
        });
        static::deleting(static function (): never {
            throw new LogicException('Inventory count sessions cannot be deleted.');
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryCountItem::class, 'session_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(InventoryCountParticipant::class, 'session_id');
    }
}
