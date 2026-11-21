<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Models;

use App\Core\Foundation\Models\BaseModel;
use LogicException;

final class InventoryIdempotencyRecord extends BaseModel
{
    protected $table = 'inventory_idempotency_records';

    protected $fillable = [
        'subject_type',
        'subject_id',
        'operation',
        'idempotency_key',
        'request_hash',
        'result',
        'completed_at',
    ];

    protected static function booted(): void
    {
        self::updating(static function (): never {
            throw new LogicException('Inventory idempotency records are immutable.');
        });
        self::deleting(static function (): never {
            throw new LogicException('Inventory idempotency records cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'result' => 'array',
            'completed_at' => 'datetime',
        ];
    }
}
