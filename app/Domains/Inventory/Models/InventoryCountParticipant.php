<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Models;

use App\Core\Foundation\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class InventoryCountParticipant extends BaseModel
{
    protected $table = 'inventory_count_participants';

    protected $fillable = [
        'session_id',
        'user_id',
        'participant_role',
        'joined_at',
    ];

    protected function casts(): array
    {
        return ['joined_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::deleting(static function (): never {
            throw new LogicException('Inventory count participants cannot be deleted.');
        });
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(InventoryCountSession::class, 'session_id');
    }
}
