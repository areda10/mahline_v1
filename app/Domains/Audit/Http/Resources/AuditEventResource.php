<?php

declare(strict_types=1);

namespace App\Domains\Audit\Http\Resources;

use App\Core\Foundation\Resources\BaseResource;
use Illuminate\Http\Request;

final class AuditEventResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_key' => $this->event_key,
            'action' => $this->action,
            'actor_type' => $this->actor_type,
            'actor_id' => $this->actor_id,
            'cooperative_id' => $this->cooperative_id,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'metadata' => $this->metadata,
            'correlation_id' => $this->correlation_id,
            'occurred_at' => $this->occurred_at?->toISOString(),
        ];
    }
}
