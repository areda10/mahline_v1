<?php

declare(strict_types=1);

namespace App\Domains\Audit\Models;

use App\Core\Foundation\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

final class AuditEvent extends BaseModel
{
    protected $table = 'audit_events';

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'metadata' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }

    protected function performUpdate(Builder $query): bool
    {
        throw new LogicException('Audit events are append-only.');
    }

    protected function performDeleteOnModel(): void
    {
        throw new LogicException('Audit events are append-only.');
    }
}
