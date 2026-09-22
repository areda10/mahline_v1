<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Authentication\Services;

use App\Domains\Identity\Authentication\Services\AuthenticationSecurityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AuthenticationSecurityServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_is_not_locked_before_maximum_failed_attempts(): void
    {
        $service = app(AuthenticationSecurityService::class);

        $email = 'user@example.com';

        $service->recordFailedAttempt($email);
        $service->recordFailedAttempt($email);
        $service->recordFailedAttempt($email);
        $service->recordFailedAttempt($email);

        self::assertFalse(
            $service->isLocked($email),
        );
    }

    public function test_account_is_locked_after_maximum_failed_attempts(): void
    {
        $service = app(AuthenticationSecurityService::class);

        $email = 'locked@example.com';

        $service->recordFailedAttempt($email);
        $service->recordFailedAttempt($email);
        $service->recordFailedAttempt($email);
        $service->recordFailedAttempt($email);
        $service->recordFailedAttempt($email);

        self::assertTrue(
            $service->isLocked($email),
        );
    }

    public function test_account_is_unlocked_after_lockout_duration(): void
    {
        $service = app(AuthenticationSecurityService::class);

        $email = 'expired-lock@example.com';

        $service->recordFailedAttempt($email);
        $service->recordFailedAttempt($email);
        $service->recordFailedAttempt($email);
        $service->recordFailedAttempt($email);
        $service->recordFailedAttempt($email);

        self::assertTrue(
            $service->isLocked($email),
        );

        $this->travel(15)->minutes();

        self::assertFalse(
            $service->isLocked($email),
        );
    }

    public function test_successful_authentication_clears_failed_attempts(): void
    {
        $service = app(AuthenticationSecurityService::class);

        $email = 'reset@example.com';

        $service->recordFailedAttempt($email);
        $service->recordFailedAttempt($email);
        $service->recordFailedAttempt($email);
        $service->recordFailedAttempt($email);

        self::assertFalse(
            $service->isLocked($email),
        );

        $service->clearFailedAttempts($email);

        self::assertFalse(
            $service->isLocked($email),
        );

        $service->recordFailedAttempt($email);

        self::assertFalse(
            $service->isLocked($email),
        );
    }

    public function test_remaining_attempts_are_calculated_correctly(): void
    {
        $service = app(AuthenticationSecurityService::class);

        $email = 'remaining@example.com';

        self::assertSame(
            5,
            $service->remainingAttempts($email),
        );

        $service->recordFailedAttempt($email);

        self::assertSame(
            4,
            $service->remainingAttempts($email),
        );

        $service->recordFailedAttempt($email);
        $service->recordFailedAttempt($email);

        self::assertSame(
            2,
            $service->remainingAttempts($email),
        );
    }

    public function test_failed_attempt_does_not_reset_an_active_lockout(): void
    {
        $service = app(AuthenticationSecurityService::class);

        $email = 'still-locked@example.com';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $service->recordFailedAttempt($email);
        }

        self::assertTrue(
            $service->isLocked($email),
        );

        $service->recordFailedAttempt($email);

        self::assertTrue(
            $service->isLocked($email),
        );

        self::assertSame(
            0,
            $service->remainingAttempts($email),
        );
    }

    public function test_failed_attempts_are_isolated_between_email_addresses(): void
    {
        $service = app(AuthenticationSecurityService::class);

        $lockedEmail = 'locked-user@example.com';
        $otherEmail = 'other-user@example.com';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $service->recordFailedAttempt($lockedEmail);
        }

        self::assertTrue(
            $service->isLocked($lockedEmail),
        );

        self::assertFalse(
            $service->isLocked($otherEmail),
        );

        self::assertSame(
            5,
            $service->remainingAttempts($otherEmail),
        );
    }

    public function test_failed_attempts_can_be_tracked_by_ip_address(): void
    {
        $service = app(AuthenticationSecurityService::class);

        $ipAddress = '192.0.2.10';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $service->recordFailedAttemptByIp($ipAddress);
        }

        self::assertTrue(
            $service->isIpLocked($ipAddress),
        );
    }

    public function test_ip_and_email_attempts_are_independent(): void
    {
        $service = app(AuthenticationSecurityService::class);

        $email = 'independent@example.com';
        $ipAddress = '192.0.2.20';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $service->recordFailedAttemptByIp($ipAddress);
        }

        self::assertTrue(
            $service->isIpLocked($ipAddress),
        );

        self::assertFalse(
            $service->isLocked($email),
        );

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $service->recordFailedAttempt($email);
        }

        self::assertTrue(
            $service->isLocked($email),
        );

        self::assertTrue(
            $service->isIpLocked($ipAddress),
        );
    }

    public function test_remaining_ip_attempts_are_calculated_correctly(): void
    {
        $service = app(AuthenticationSecurityService::class);

        $ipAddress = '192.0.2.30';

        self::assertSame(
            5,
            $service->remainingIpAttempts($ipAddress),
        );

        $service->recordFailedAttemptByIp($ipAddress);

        self::assertSame(
            4,
            $service->remainingIpAttempts($ipAddress),
        );

        $service->recordFailedAttemptByIp($ipAddress);
        $service->recordFailedAttemptByIp($ipAddress);

        self::assertSame(
            2,
            $service->remainingIpAttempts($ipAddress),
        );
    }

    public function test_successful_authentication_can_clear_failed_ip_attempts(): void
    {
        $service = app(AuthenticationSecurityService::class);

        $ipAddress = '192.0.2.40';

        $service->recordFailedAttemptByIp($ipAddress);
        $service->recordFailedAttemptByIp($ipAddress);
        $service->recordFailedAttemptByIp($ipAddress);
        $service->recordFailedAttemptByIp($ipAddress);

        self::assertSame(
            1,
            $service->remainingIpAttempts($ipAddress),
        );

        $service->clearFailedIpAttempts($ipAddress);

        self::assertSame(
            5,
            $service->remainingIpAttempts($ipAddress),
        );

        self::assertFalse(
            $service->isIpLocked($ipAddress),
        );
    }

    public function test_lockout_seconds_remaining_is_positive_when_account_is_locked(): void
    {
        $service = app(AuthenticationSecurityService::class);

        $email = 'lockout-time@example.com';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $service->recordFailedAttempt($email);
        }

        self::assertTrue(
            $service->isLocked($email),
        );

        self::assertGreaterThan(
            0,
            $service->lockoutSecondsRemaining($email),
        );
    }

    public function test_ip_lockout_seconds_remaining_is_positive_when_ip_is_locked(): void
    {
        $service = app(AuthenticationSecurityService::class);

        $ipAddress = '192.0.2.50';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $service->recordFailedAttemptByIp($ipAddress);
        }

        self::assertTrue(
            $service->isIpLocked($ipAddress),
        );

        self::assertGreaterThan(
            0,
            $service->ipLockoutSecondsRemaining($ipAddress),
        );
    }
}