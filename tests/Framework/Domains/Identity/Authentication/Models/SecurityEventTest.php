<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Authentication\Models;

use \App\Domains\Identity\Authentication\Models\SecurityEvent;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class SecurityEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_events_table_exists(): void
    {
        self::assertTrue(
            Schema::hasTable('security_events'),
        );
    }

    public function test_security_events_table_has_required_columns(): void
    {
        self::assertTrue(
            Schema::hasColumns('security_events', [
                'id',
                'user_id',
                'event',
                'reason',
                'ip_address',
                'user_agent',
                'browser',
                'device',
                'metadata',
                'occurred_at',
                'created_at',
                'updated_at',
            ]),
        );
    }

    public function test_security_event_can_be_created_without_user(): void
    {
        $event = SecurityEvent::query()->create([
            'user_id' => null,
            'event' => 'security_incident',
            'reason' => 'anonymous_security_event',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'TestAgent',
            'browser' => 'Firefox',
            'device' => 'Desktop',
            'metadata' => [
                'source' => 'test',
            ],
            'occurred_at' => now(),
        ]);

        self::assertNotEmpty($event->getKey());
        self::assertNull($event->user_id);
        self::assertSame('security_incident', $event->event);
    }

    public function test_security_event_belongs_to_user(): void
    {
        $user = User::factory()->create();

        $event = SecurityEvent::query()->create([
            'user_id' => $user->id,
            'event' => 'account_locked',
            'reason' => 'too_many_failed_attempts',
            'occurred_at' => now(),
        ]);

        self::assertTrue($event->user->is($user));
    }

    public function test_security_event_casts_metadata_and_occurred_at(): void
    {
        $event = SecurityEvent::query()->create([
            'event' => 'security_incident',
            'metadata' => [
                'source' => 'test',
                'attempts' => 5,
            ],
            'occurred_at' => now(),
        ]);

        self::assertIsArray($event->metadata);
        self::assertSame('test', $event->metadata['source']);
        self::assertSame(5, $event->metadata['attempts']);

        self::assertInstanceOf(
            \Illuminate\Support\Carbon::class,
            $event->occurred_at,
        );
    }

    public function test_security_event_persists_all_security_context(): void
    {
        $event = SecurityEvent::query()->create([
            'event' => 'brute_force_detected',
            'reason' => 'too_many_failed_attempts',
            'ip_address' => '192.168.1.10',
            'user_agent' => 'Mozilla/5.0',
            'browser' => 'Firefox',
            'device' => 'Desktop',
            'metadata' => [
                'attempts' => 5,
                'window' => 900,
            ],
            'occurred_at' => now(),
        ]);

        $this->assertDatabaseHas('security_events', [
            'id' => $event->id,
            'event' => 'brute_force_detected',
            'reason' => 'too_many_failed_attempts',
            'ip_address' => '192.168.1.10',
            'browser' => 'Firefox',
            'device' => 'Desktop',
        ]);
    }

    public function test_security_event_belongs_to_user_with_real_user(): void
    {
        $user = User::factory()->create();

        $event = SecurityEvent::query()->create([
            'user_id' => $user->id,
            'event' => 'account_locked',
            'reason' => 'too_many_failed_attempts',
            'occurred_at' => now(),
        ]);

        $this->assertDatabaseHas('security_events', [
            'id' => $event->id,
            'user_id' => $user->id,
        ]);

        self::assertSame(
            $user->id,
            $event->user->id,
        );
    }

    public function test_security_event_user_id_becomes_null_when_user_is_force_deleted(): void
    {
        $user = User::factory()->create();

        $event = SecurityEvent::query()->create([
            'user_id' => $user->id,
            'event' => 'account_locked',
            'reason' => 'too_many_failed_attempts',
            'occurred_at' => now(),
        ]);

        $user->forceDelete();

        $event->refresh();

        self::assertNull($event->user_id);

        $this->assertDatabaseHas('security_events', [
            'id' => $event->id,
            'user_id' => null,
        ]);
    }

    public function test_security_event_requires_occurred_at(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        SecurityEvent::query()->create([
            'event' => 'security_incident',
            'reason' => 'missing_occurred_at',
        ]);
    }
}