<?php

declare(strict_types=1);

namespace App\\Domains\\Audit\\Repositories;

use App\\Domains\\Audit\\Models\\AuditEvent;
use Illuminate\\Support\\Facades\\DB;
use JsonException;

final class AuditEventRepository
{
    /**
     * Append an immutable event to the serialized SHA-256 audit chain.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws JsonException
     */
    public function append(array $attributes): AuditEvent
    {
        return DB::transaction(function () use ($attributes): AuditEvent {
            $head = DB::table('audit_chain_heads')
                ->where('id', 1)
                ->lockForUpdate()
                ->first();

            if ($head === null) {
                throw new \\LogicException('The audit chain head has not been initialized.');
            }

            $previousHash = $head->last_hash;
            $hashPayload = [
                'event_key' => $attributes['event_key'],
                'action' => $attributes['action'],
                'actor_type' => $attributes['actor_type'] ?? null,
                'actor_id' => $attributes['actor_id'] ?? null,
                'cooperative_id' => $attributes['cooperative_id'] ?? null,
                'subject_type' => $attributes['subject_type'] ?? null,
                'subject_id' => $attributes['subject_id'] ?? null,
                'old_values' => $attributes['old_values'] ?? null,
                'new_values' => $attributes['new_values'] ?? null,
                'metadata' => $attributes['metadata'] ?? null,
                'ip_address' => $attributes['ip_address'] ?? null,
                'user_agent' => $attributes['user_agent'] ?? null,
                'correlation_id' => $attributes['correlation_id'] ?? null,
                'occurred_at' => ($attributes['occurred_at'] ?? now())->format('Y-m-d H:i:s.u'),
            ];

            $canonicalPayload = json_encode(
                $this->canonicalize($hashPayload),
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            );

            $eventHash = hash(
                'sha256',
                ($previousHash ?? str_repeat('0', 64))."\n".$canonicalPayload,
            );

            $event = AuditEvent::query()->create([
                ...$attributes,
                'previous_hash' => $previousHash,
                'event_hash' => $eventHash,
            ]);

            DB::table('audit_chain_heads')
                ->where('id', 1)
                ->update([
                    'last_hash' => $eventHash,
                    'updated_at' => now(),
                ]);

            return $event;
        });
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        foreach ($value as $key => $nested) {
            $value[$key] = $this->canonicalize($nested);
        }

        return $value;
    }
}
