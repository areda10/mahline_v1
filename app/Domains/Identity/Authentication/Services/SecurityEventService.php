<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\Services;

use App\Core\Foundation\Services\BaseService;
use App\Domains\Identity\Authentication\Enums\SecurityEventType;
use App\Domains\Identity\Authentication\Models\SecurityEvent;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

final class SecurityEventService extends BaseService
{
    private const SENSITIVE_KEYS = [
        'password', 'password_confirmation', 'current_password', 'new_password',
        'temporary_password', 'password_hash', 'token', 'access_token',
        'refresh_token', 'plain_text_token', 'reset_token', 'verification_token',
        'secret', 'client_secret', 'api_key', 'authorization', 'cookie',
        'set_cookie', 'session', 'session_id', 'session_secret', 'remember_token',
        'private_key', 'otp', 'otp_code', 'one_time_password', 'recovery_code',
        'verification_code', 'email_verification_code', 'pin',
    ];

    public function record(
        SecurityEventType $event,
        ?string $reason = null,
        ?User $user = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $browser = null,
        ?string $device = null,
        ?array $metadata = null,
        ?Carbon $occurredAt = null,
    ): SecurityEvent {
        return SecurityEvent::query()->create([
            'user_id' => $user?->id,
            'event' => $event,
            'reason' => $reason,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'browser' => $browser,
            'device' => $device,
            'metadata' => $this->sanitize($metadata),
            'occurred_at' => $occurredAt ?? now(),
        ]);
    }

    private function sanitize(?array $metadata): ?array
    {
        if ($metadata === null) {
            return null;
        }

        $safe = [];
        foreach ($metadata as $key => $value) {
            if (is_string($key)) {
                $normalized = Str::snake(str_replace(['-', ' '], '_', $key));
                if (
                    in_array($normalized, self::SENSITIVE_KEYS, true)
                    || str_ends_with($normalized, '_password')
                    || str_ends_with($normalized, '_token')
                    || str_ends_with($normalized, '_secret')
                    || str_ends_with($normalized, '_token_hash')
                ) {
                    continue;
                }
            }

            $safe[$key] = is_array($value) ? $this->sanitize($value) : $value;
        }

        return $safe;
    }
}
