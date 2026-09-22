<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\Services;

use App\Core\Foundation\Services\BaseService;
use App\Domains\Identity\Authentication\Enums\SecurityEventType;
use App\Domains\Identity\Authentication\Models\SecurityEvent;
use App\Domains\Identity\Users\Models\User;

final class SecurityEventService extends BaseService
{
    public function record(
        SecurityEventType $event,
        ?string $reason = null,
        ?User $user = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $browser = null,
        ?string $device = null,
        ?array $metadata = null,
        ?\Illuminate\Support\Carbon $occurredAt = null,
    ): SecurityEvent {
        return SecurityEvent::query()->create([
            'user_id' => $user?->id,    
            'event' => $event,
            'reason' => $reason,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'browser' => $browser,
            'device' => $device,
            'metadata' => $metadata,
            'occurred_at' => $occurredAt ?? now(),
        ]);
    }
}