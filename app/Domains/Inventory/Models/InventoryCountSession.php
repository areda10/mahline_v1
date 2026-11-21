<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Models;

use App\Core\Foundation\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function items(): HasMany
    {
        return $this->hasMany(InventoryCountItem::class, 'session_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(InventoryCountParticipant::class, 'session_id');
    }
}
