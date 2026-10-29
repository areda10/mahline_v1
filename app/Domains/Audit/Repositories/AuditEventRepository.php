<?php

declare(strict_types=1);

namespace App\Domains\Audit\Repositories;

use App\Domains\Audit\Models\AuditEvent;

final class AuditEventRepository
{
    /**
     * Persist a new event. Existing events are never updated by this repository.
     *
     * @param array<string, mixed> $attributes
     */
    public function append(array $attributes): AuditEvent
    {
        return AuditEvent::query()->create($attributes);
    }
}
