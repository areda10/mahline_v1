<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Authentication\Services;

use App\Domains\Identity\Authentication\Enums\SecurityEventType;
use App\Domains\Identity\Authentication\Models\SecurityEvent;
use App\Domains\Identity\Authentication\Services\SecurityEventService;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SecurityEventServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_records_an_anonymous_security_event(): void
    {
        $service = app(SecurityEventService::class);

        $event = $service->record(
            event: SecurityEventType::SecurityIncident,
            reason: 'anonymous_security_event',
        );

        self::assertInstanceOf(
            SecurityEvent::class,
            $event,
        );

        self::assertNull($event->user_id);

        self::assertSame(
            SecurityEventType::SecurityIncident,
            $event->event,
        );

        self::assertSame(
            'anonymous_security_event',
            $event->reason,
        );
    }

    public function test_it_records_a_security_event_for_a_user(): void
    {
        $user = User::factory()->create();

        $service = app(SecurityEventService::class);

        $event = $service->record(
            event: SecurityEventType::AccountLocked,
            reason: 'too_many_failed_attempts',
            user: $user,
        );

        self::assertSame(
            $user->id,
            $event->user_id,
        );

        self::assertTrue(
            $event->user->is($user),
        );
    }

    public function test_it_records_the_ip_address(): void
    {
        $service = app(SecurityEventService::class);

        $event = $service->record(
            event: SecurityEventType::SecurityIncident,
            reason: 'suspicious_ip',
            ipAddress: '192.168.1.100',
        );

        self::assertSame(
            '192.168.1.100',
            $event->ip_address,
        );
    }

    public function test_it_records_the_user_agent(): void
    {
        $service = app(SecurityEventService::class);

        $event = $service->record(
            event: SecurityEventType::SecurityIncident,
            reason: 'suspicious_user_agent',
            userAgent: 'Mozilla/5.0 Test Browser',
        );

        self::assertSame(
            'Mozilla/5.0 Test Browser',
            $event->user_agent,
        );
    }

    public function test_it_records_the_browser(): void
    {
        $service = app(SecurityEventService::class);

        $event = $service->record(
            event: SecurityEventType::SecurityIncident,
            reason: 'browser_detected',
            browser: 'Firefox',
        );

        self::assertSame(
            'Firefox',
            $event->browser,
        );
    }

    public function test_it_records_the_device(): void
    {
        $service = app(SecurityEventService::class);

        $event = $service->record(
            event: SecurityEventType::SecurityIncident,
            reason: 'device_detected',
            device: 'Desktop',
        );

        self::assertSame(
            'Desktop',
            $event->device,
        );
    }

    public function test_it_records_metadata(): void
    {
        $service = app(SecurityEventService::class);

        $metadata = [
            'attempts' => 5,
            'source' => 'authentication',
        ];

        $event = $service->record(
            event: SecurityEventType::BruteForceDetected,
            reason: 'too_many_failed_attempts',
            metadata: $metadata,
        );

        self::assertSame(
            $metadata,
            $event->metadata,
        );
    }

    public function test_it_persists_the_complete_security_event(): void
    {
        $user = User::factory()->create();

        $service = app(SecurityEventService::class);

        $event = $service->record(
            event: SecurityEventType::BruteForceDetected,
            reason: 'too_many_failed_attempts',
            user: $user,
            ipAddress: '192.168.1.100',
            userAgent: 'Mozilla/5.0 Test Browser',
            browser: 'Firefox',
            device: 'Desktop',
            metadata: [
                'attempts' => 5,
                'window' => 900,
            ],
        );

        $this->assertDatabaseHas('security_events', [
            'id' => $event->id,
            'user_id' => $user->id,
            'event' => 'brute_force_detected',
            'reason' => 'too_many_failed_attempts',
            'ip_address' => '192.168.1.100',
            'user_agent' => 'Mozilla/5.0 Test Browser',
            'browser' => 'Firefox',
            'device' => 'Desktop',
        ]);

        self::assertSame(
            [
                'attempts' => 5,
                'window' => 900,
            ],
            $event->metadata,
        );
    }

    public function test_it_records_a_custom_occurred_at(): void
    {
        $service = app(SecurityEventService::class);

        $occurredAt = now()->subMinutes(10);

        $event = $service->record(
            event: SecurityEventType::SecurityIncident,
            reason: 'historical_event',
            occurredAt: $occurredAt,
        );

        self::assertSame(
            $occurredAt->format('Y-m-d H:i:s'),
            $event->occurred_at->format('Y-m-d H:i:s'),
        );
    }

    public function test_security_event_service_does_not_depend_on_request(): void
    {
        $reflection = new \ReflectionClass(SecurityEventService::class);

        $constructor = $reflection->getConstructor();

        self::assertNull($constructor);
    }

    public function test_it_accepts_a_security_event_type(): void
    {
        $service = app(SecurityEventService::class);

        $event = $service->record(
            event: SecurityEventType::AccountLocked,
            reason: 'too_many_failed_attempts',
        );

        self::assertSame(
            SecurityEventType::AccountLocked,
            $event->event,
        );
    }
}