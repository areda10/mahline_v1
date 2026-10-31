<?php

declare(strict_types=1);

namespace App\Domains\Audit\Services;

use App\Domains\Audit\Models\AuditEvent;
use App\Domains\Audit\Repositories\AuditEventRepository;
use App\Shared\DTOs\AuditEntryDTO;
use Illuminate\Http\Request;
use InvalidArgumentException;

final class AuditService
{
    public function __construct(
        private readonly AuditEventRepository $events,
        private readonly Request $request,
    ) {
    }

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
            throw new InvalidArgumentException(
                'Audit event key and action are required.'
            );
        }

        $request = app()->bound('request')
            ? app('request')
            : null;

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

            'ip_address' => $entry->ipAddress
                ?? $request?->ip(),

            'user_agent' => $entry->userAgent
                ?? $request?->userAgent(),

            'correlation_id' => $entry->correlationId
                ?? $request?->attributes->get('correlation_id'),

            'occurred_at' => $entry->occurredAt ?? now(),
        ]);
    }

    private function sanitize(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $safe = [];

        foreach ($values as $key => $value) {
            if (
                is_string($key)
                && in_array(
                    strtolower($key),
                    self::SENSITIVE_KEYS,
                    true,
                )
            ) {
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