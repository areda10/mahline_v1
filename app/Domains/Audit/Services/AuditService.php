<?php

declare(strict_types=1);

namespace App\Domains\Audit\Services;

use App\Domains\Audit\Models\AuditEvent;
use App\Shared\DTOs\AuditEntryDTO;
use InvalidArgumentException;

final class AuditService
{
    /**
     * Keys that must never be persisted in audit payloads.
     *
     * This is a defence-in-depth denylist; callers must still avoid passing
     * secrets and unnecessary personal data to the audit API.
     *
     * @var list<string>
     */
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'token',
        'access_token',
        'refresh_token',
        'plain_text_token',
        'secret',
        'client_secret',
        'api_key',
        'authorization',
        'cookie',
        'set-cookie',
        'session',
        'session_id',
        'session_secret',
        'remember_token',
        'private_key',
    ];

    public function record(AuditEntryDTO $entry): AuditEvent
    {
        if (trim($entry->eventKey) === '' || trim($entry->action) === '') {
            throw new InvalidArgumentException('Audit event key and action are required.');
        }

        return AuditEvent::query()->create([
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
            'ip_address' => $entry->ipAddress,
            'user_agent' => $entry->userAgent,
            'correlation_id' => $entry->correlationId,
            'occurred_at' => $entry->occurredAt,
        ]);
    }

    private function sanitize(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $safe = [];

        foreach ($values as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::SENSITIVE_KEYS, true)) {
                continue;
            }

            if (is_array($value)) {
                $safe[$key] = $this->sanitize($value);
                continue;
            }

            $safe[$key] = $value;
        }

        return $safe;
    }
}
