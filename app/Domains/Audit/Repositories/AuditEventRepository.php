<?php

declare(strict_types=1);

namespace App\Domains\Audit\Repositories;

use App\Domains\Audit\Models\AuditEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
                throw new \LogicException('The audit chain head has not been initialized.');
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

    /**
     * Verify the complete chain from its current head back to the genesis event.
     */
    public function verifyIntegrity(): bool
    {
        return DB::transaction(function (): bool {
            $head = DB::table('audit_chain_heads')
                ->where('id', 1)
                ->lockForUpdate()
                ->first();

            if ($head === null) {
                return false;
            }

            $total = DB::table('audit_events')->count();
            $currentHash = $head->last_hash;

            if ($currentHash === null) {
                return $total === 0;
            }

            $visited = 0;
            while ($currentHash !== null) {
                $event = DB::table('audit_events')
                    ->where('event_hash', $currentHash)
                    ->first();

                if ($event === null) {
                    return false;
                }

                $payload = [
                    'event_key' => $event->event_key,
                    'action' => $event->action,
                    'actor_type' => $event->actor_type,
                    'actor_id' => $event->actor_id,
                    'cooperative_id' => $event->cooperative_id,
                    'subject_type' => $event->subject_type,
                    'subject_id' => $event->subject_id,
                    'old_values' => $this->decodeJson($event->old_values),
                    'new_values' => $this->decodeJson($event->new_values),
                    'metadata' => $this->decodeJson($event->metadata),
                    'ip_address' => $event->ip_address,
                    'user_agent' => $event->user_agent,
                    'correlation_id' => $event->correlation_id,
                    'occurred_at' => Carbon::parse($event->occurred_at)->format('Y-m-d H:i:s.u'),
                ];

                $canonicalPayload = json_encode(
                    $this->canonicalize($payload),
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
                );
                $expectedHash = hash(
                    'sha256',
                    ($event->previous_hash ?? str_repeat('0', 64))."\n".$canonicalPayload,
                );

                if (! hash_equals($expectedHash, $event->event_hash)) {
                    return false;
                }

                $visited++;
                if ($visited > $total) {
                    return false;
                }

                $currentHash = $event->previous_hash;
            }

            return $visited === $total;
        });
    }

    /** @return array<mixed>|null */
    private function decodeJson(?string $value): ?array
    {
        return $value === null
            ? null
            : json_decode($value, true, 512, JSON_THROW_ON_ERROR);
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
