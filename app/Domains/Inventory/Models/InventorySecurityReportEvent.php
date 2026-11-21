<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Models;

use App\Core\Foundation\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class InventorySecurityReportEvent extends BaseModel
{
    protected $table = 'inventory_security_report_events';

    protected $fillable = [
        'report_id', 'event_type', 'old_status', 'new_status', 'reason',
        'actor_id', 'actor_type', 'metadata', 'occurred_at',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'occurred_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Inventory security report events are immutable.');
        });
        static::deleting(static function (): never {
            throw new LogicException('Inventory security report events cannot be deleted.');
        });
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(InventorySecurityReport::class, 'report_id');
    }
}
