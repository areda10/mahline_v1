<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Authentication\Enums;

use App\Domains\Identity\Authentication\Enums\SecurityEventType;
use Tests\TestCase;

final class SecurityEventTypeTest extends TestCase
{
    public function test_security_event_types_contains_required_events(): void
    {
        self::assertSame(
            'account_locked',
            SecurityEventType::AccountLocked->value,
        );

        self::assertSame(
            'brute_force_detected',
            SecurityEventType::BruteForceDetected->value,
        );

        self::assertSame(
            'unusual_activity_detected',
            SecurityEventType::UnusualActivityDetected->value,
        );

        self::assertSame(
            'global_logout',
            SecurityEventType::GlobalLogout->value,
        );

        self::assertSame(
            'security_incident',
            SecurityEventType::SecurityIncident->value,
        );
    }
}