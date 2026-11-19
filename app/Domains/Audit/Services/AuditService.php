<?php

declare(strict_types=1);

namespace App\Domains\Audit\Services;

use App\Domains\Audit\Contracts\AuditWriter;
use App\Domains\Audit\Models\AuditEvent;
use App\Domains\Audit\Repositories\AuditEventRepository;
use App\Shared\DTOs\AuditEntryDTO;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class AuditService implements AuditWriter
{
    public function __construct(
        private readonly AuditEventRepository $events,
    ) {}

    private const SENSITIVE_KEYS = [
        'password', 'password_confirmation', 'current_password', 'new_password',
        'temporary_password', 'password_hash', 'token', 'access_token',
        'refresh_token', 'plain_text_token', 'reset_token', 'verification_token',
        'secret', 'client_secret', 'api_key', 'authorization', 'cookie',
        'set_cookie', 'session', 'session_id', 'session_secret', 'remember_token',
        'private_key', 'otp', 'otp_code', 'one_time_password', 'recovery_code',
        'verification_code', 'email_verification_code', 'pin',
    ];

    public function record(AuditEntryDTO $entry): AuditEvent
    {
        if (trim($entry->eventKey) === '' || trim($entry->action) === '') {
            throw new InvalidArgumentException('Audit event key and action are required.');
        }

        $request = app()->bound('request') ? app('request') : null;
        if (! $request instanceof Request) {
            $request = null;
        }

        return $this->events->append([
            'event_key' => $entry->eventKey,
            'action' => $entry->action,
            'actor_type' => $entry->actorType,
            'actor_id' => $entry->actorId,
            'cooperative_id' => $entry->cooperativeId,
            'subject_type' => $entry->subjectType,
            'subject_id' => $entry->subjectId,
            'old_values' => $this->sanitize($entry->oldValues),
            'new_values' => $this->sanitize($entry->newValues),
            'metadata' => $this->sanitize($entry->metadata),
            'ip_address' => $entry->ipAddress ?? $request?->ip(),
            'user_agent' => $entry->userAgent ?? $request?->userAgent(),
            'correlation_id' => $entry->correlationId ?? $request?->attributes->get('correlation_id'),
            'occurred_at' => $entry->occurredAt ?? now(),
        ]);
    }

    public function verifyIntegrity(): bool
    {
        return $this->events->verifyIntegrity();
    }

    private function sanitize(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $safe = [];
        foreach ($values as $key => $value) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                continue;
            }

            $safe[$key] = is_array($value) ? $this->sanitize($value) : $value;
        }

        return $safe;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = Str::snake(str_replace(['-', ' '], '_', $key));

        return in_array($normalized, self::SENSITIVE_KEYS, true)
            || str_ends_with($normalized, '_password')
            || str_ends_with($normalized, '_token')
            || str_ends_with($normalized, '_secret')
            || str_ends_with($normalized, '_token_hash');
    }
}
