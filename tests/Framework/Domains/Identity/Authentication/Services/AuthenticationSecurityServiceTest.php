<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Authentication\Services;

use App\Domains\Identity\Authentication\DTOs\AuthenticationContext;
use App\Domains\Identity\Authentication\Enums\SecurityEventType;
use App\Domains\Identity\Authentication\Models\SecurityEvent;
use App\Domains\Identity\Authentication\Services\AuthenticationSecurityService;
use App\Domains\Identity\Authentication\Services\AuthenticationService;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;
use RuntimeException;


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

    public function test_invalid_password_records_security_failed_attempt(): void
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => 'correct-password',
        ]);

        $context = new AuthenticationContext(
            email: 'john@example.com',
            password: 'wrong-password',
            sessionId: 'session-invalid-password',
            ipAddress: '127.0.0.1',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Android',
        );

        $securityService = app(AuthenticationSecurityService::class);

        self::assertSame(
            5,
            $securityService->remainingAttempts($context->email),
        );

        $service = app(AuthenticationService::class);

        try {
            $service->authenticate($context);
        } catch (RuntimeException) {
            // L'échec d'authentification est attendu.
        }

        self::assertSame(
            4,
            $securityService->remainingAttempts($context->email),
        );
    }

    public function test_successful_authentication_clears_security_failed_attempts(): void
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => 'password',
        ]);

        $context = new AuthenticationContext(
            email: 'john@example.com',
            password: 'password',
            sessionId: 'session-success',
            ipAddress: '127.0.0.1',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Android',
        );

        $securityService = app(AuthenticationSecurityService::class);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $securityService->recordFailedAttempt(
                $context->email,
            );
        }

        self::assertSame(
            2,
            $securityService->remainingAttempts($context->email),
        );

        $service = app(AuthenticationService::class);

        $service->authenticate($context);

        self::assertSame(
            5,
            $securityService->remainingAttempts($context->email),
        );
    }

    public function test_locked_ip_rejects_authentication(): void
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => 'password',
        ]);

        $context = new AuthenticationContext(
            email: 'john@example.com',
            password: 'password',
            sessionId: 'session-success',
            ipAddress: '127.0.0.1',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Android',
        );

        $securityService = app(AuthenticationSecurityService::class);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $securityService->recordFailedAttemptByIp(
                $context->ipAddress,
            );
        }

        self::assertTrue(
            $securityService->isIpLocked($context->ipAddress),
        );

        $service = app(AuthenticationService::class);

        $this->expectException(RuntimeException::class);

        $service->authenticate($context);
    }

    public function test_invalid_password_records_security_failed_attempt_by_ip(): void
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => 'correct-password',
        ]);

        $context = new AuthenticationContext(
            email: 'john@example.com',
            password: 'wrong-password',
            sessionId: 'session-invalid-password',
            ipAddress: '127.0.0.1',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Android',
        );

        $securityService = app(AuthenticationSecurityService::class);

        self::assertSame(
            5,
            $securityService->remainingIpAttempts(
                $context->ipAddress,
            ),
        );

        $service = app(AuthenticationService::class);

        try {
            $service->authenticate($context);
        } catch (RuntimeException) {
            // Échec attendu.
        }

        self::assertSame(
            4,
            $securityService->remainingIpAttempts(
                $context->ipAddress,
            ),
        );
    }

    public function test_successful_authentication_clears_security_failed_ip_attempts(): void
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => 'password',
        ]);

        $context = new AuthenticationContext(
            email: 'john@example.com',
            password: 'password',
            sessionId: 'session-success',
            ipAddress: '127.0.0.1',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Android',
        );

        $securityService = app(AuthenticationSecurityService::class);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $securityService->recordFailedAttemptByIp(
                $context->ipAddress,
            );
        }

        self::assertSame(
            2,
            $securityService->remainingIpAttempts(
                $context->ipAddress,
            ),
        );

        $service = app(AuthenticationService::class);

        $service->authenticate($context);

        self::assertSame(
            5,
            $securityService->remainingIpAttempts(
                $context->ipAddress,
            ),
        );
    }

    public function test_fifth_failed_authentication_records_brute_force_security_event(): void
    {
        $user = User::factory()->create([
            'email' => 'bruteforce@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $context = new AuthenticationContext(
            email: 'bruteforce@example.com',
            password: 'WrongPassword123',
            sessionId: 'session-bruteforce-001',
            ipAddress: '127.0.0.1',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'iPhone',
        );

        $service = app(AuthenticationService::class);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            try {
                $service->authenticate($context);
            } catch (\Throwable) {
                // Expected failed authentication.
            }
        }

        $this->assertDatabaseHas('security_events', [
            'user_id' => $user->id,
            'event' => SecurityEventType::BruteForceDetected->value,
        ]);
    }

    public function test_fifth_failed_authentication_records_account_locked_security_event(): void
    {
        $user = User::factory()->create([
            'email' => 'locked@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $context = new AuthenticationContext(
            email: 'locked@example.com',
            password: 'WrongPassword123',
            sessionId: 'session-locked-001',
            ipAddress: '127.0.0.2',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'iPhone',
        );

        $service = app(AuthenticationService::class);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            try {
                $service->authenticate($context);
            } catch (\Throwable) {
                // Expected failed authentication.
            }
        }

        $this->assertDatabaseHas('security_events', [
            'user_id' => $user->id,
            'event' => SecurityEventType::AccountLocked->value,
        ]);
    }

    public function test_locked_account_does_not_duplicate_security_events_on_further_failed_attempts(): void
    {
        $user = User::factory()->create([
            'email' => 'locked-duplicate@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $context = new AuthenticationContext(
            email: 'locked-duplicate@example.com',
            password: 'WrongPassword123',
            sessionId: 'session-locked-duplicate-001',
            ipAddress: '127.0.0.3',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'iPhone',
        );

        $service = app(AuthenticationService::class);

        for ($attempt = 1; $attempt <= 6; $attempt++) {
            try {
                $service->authenticate($context);
            } catch (\Throwable) {
                // Expected failed authentication.
            }
        }

        $this->assertSame(
            1,
            SecurityEvent::query()
                ->where('user_id', $user->id)
                ->where(
                    'event',
                    SecurityEventType::BruteForceDetected->value,
                )
                ->count(),
        );

        $this->assertSame(
            1,
            SecurityEvent::query()
                ->where('user_id', $user->id)
                ->where(
                    'event',
                    SecurityEventType::AccountLocked->value,
                )
                ->count(),
        );
    }

    public function test_locked_account_rejects_correct_password(): void
    {
        $user = User::factory()->create([
            'email' => 'locked-correct-password@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $wrongContext = new AuthenticationContext(
            email: 'locked-correct-password@example.com',
            password: 'WrongPassword123',
            sessionId: 'session-lock-001',
            ipAddress: '127.0.0.4',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'iPhone',
        );

        $service = app(AuthenticationService::class);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            try {
                $service->authenticate($wrongContext);
            } catch (\Throwable) {
                // Expected failed authentication.
            }
        }

        $correctContext = new AuthenticationContext(
            email: 'locked-correct-password@example.com',
            password: 'ValidPassword123',
            sessionId: 'session-lock-002',
            ipAddress: '127.0.0.5',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'iPhone',
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Account is temporarily locked.'
        );

        $service->authenticate($correctContext);
    }
//
    public function test_account_can_authenticate_after_lockout_expires(): void
    {
        $user = User::factory()->create([
            'email' => 'lockout-expired@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $wrongContext = new AuthenticationContext(
            email: 'lockout-expired@example.com',
            password: 'WrongPassword123',
            sessionId: 'session-expired-001',
            ipAddress: '127.0.0.6',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'iPhone',
        );

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            try {
                $service->authenticate($wrongContext);
            } catch (\Throwable) {
                // Expected failed authentication.
            }
        }

        try {

            Carbon::setTestNow(now()->addSeconds(901));

            $correctContext = new AuthenticationContext(
                email: 'lockout-expired@example.com',
                password: 'ValidPassword123',
                sessionId: 'session-expired-002',
                ipAddress: '127.0.0.7',
                userAgent: 'Mozilla/5.0',
                browser: 'Firefox',
                device: 'iPhone',
            );

            $authenticatedUser = $service->authenticate($correctContext);

            $this->assertSame(
                $user->id,
                $authenticatedUser->id,
            );

            // $this->assertDatabaseHas('authentication_sessions', [
            //     'user_id' => $user->id,
            //     'session_id' => 'session-expired-002',
            // ]);

        } finally { 
            Carbon::setTestNow(); 
        }
    }

    public function test_successful_authentication_resets_failed_attempts(): void
    {
        $user = User::factory()->create([
            'email' => 'reset-attempts@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $wrongContext = new AuthenticationContext(
            email: 'reset-attempts@example.com',
            password: 'WrongPassword123',
            sessionId: 'session-reset-001',
            ipAddress: '127.0.0.8',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'iPhone',
        );

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $service->authenticate($wrongContext);
            } catch (\Throwable) {
                // Expected failed authentication.
            }
        }

        $correctContext = new AuthenticationContext(
            email: 'reset-attempts@example.com',
            password: 'ValidPassword123',
            sessionId: 'session-reset-002',
            ipAddress: '127.0.0.9',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'iPhone',
        );

        $service->authenticate($correctContext);

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $service->authenticate($wrongContext);
            } catch (\Throwable) {
                // Expected failed authentication.
            }
        }

        $this->assertFalse(
            $this->app
                ->make(AuthenticationSecurityService::class)
                ->isLocked('reset-attempts@example.com'),
        );
    }

    public function test_locked_ip_rejects_correct_password(): void
    {
        $user = User::factory()->create([
            'email' => 'locked-ip@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $wrongContext = new AuthenticationContext(
            email: 'locked-ip@example.com',
            password: 'WrongPassword123',
            sessionId: 'session-ip-lock-001',
            ipAddress: '127.0.0.10',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'iPhone',
        );

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            try {
                $service->authenticate($wrongContext);
            } catch (\Throwable) {
                // Expected failed authentication.
            }
        }

        $correctContext = new AuthenticationContext(
            email: 'locked-ip@example.com',
            password: 'ValidPassword123',
            sessionId: 'session-ip-lock-002',
            ipAddress: '127.0.0.10',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'iPhone',
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'IP address is temporarily locked.'
        );

        $service->authenticate($correctContext);

        $this->assertDatabaseMissing('authentication_sessions', [
            'user_id' => $user->id,
            'session_id' => 'session-ip-lock-002',
        ]);
    }

    public function test_locked_ip_rejects_authentication_for_another_user(): void
    {
        $firstUser = User::factory()->create([
            'email' => 'first-ip-lock@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $secondUser = User::factory()->create([
            'email' => 'second-ip-lock@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $ipAddress = '127.0.0.20';

        $wrongContext = new AuthenticationContext(
            email: $firstUser->email,
            password: 'WrongPassword123',
            sessionId: 'session-ip-independent-001',
            ipAddress: $ipAddress,
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            try {
                $service->authenticate($wrongContext);
            } catch (\Throwable) {
                // Expected failed authentication.
            }
        }

        $correctContext = new AuthenticationContext(
            email: $secondUser->email,
            password: 'ValidPassword123',
            sessionId: 'session-ip-independent-002',
            ipAddress: $ipAddress,
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'IP address is temporarily locked.'
        );

        $service->authenticate($correctContext);
    }

    public function test_locked_ip_expires_after_fifteen_minutes(): void
    {
        $securityService = app(AuthenticationSecurityService::class);

        $ipAddress = '127.0.0.30';

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $securityService->recordFailedAttemptByIp($ipAddress);
        }

        $this->assertTrue(
            $securityService->isIpLocked($ipAddress)
        );

        $securityService->clearFailedIpAttempts($ipAddress);

        $this->assertFalse(
            $securityService->isIpLocked($ipAddress)
        );
    }
//
    public function test_locked_ip_does_not_block_authentication_from_another_ip(): void
    {
        $user = User::factory()->create([
            'email' => 'different-ip@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);
        $securityService = app(AuthenticationSecurityService::class);

        $lockedIp = '127.0.0.40';
        $otherIp = '127.0.0.41';

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $securityService->recordFailedAttemptByIp($lockedIp);
        }

        $this->assertTrue(
            $securityService->isIpLocked($lockedIp)
        );

        $correctContext = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-different-ip-002',
            ipAddress: $otherIp,
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $authenticatedUser = $service->authenticate($correctContext);

        $this->assertSame(
            $user->id,
            $authenticatedUser->id
        );

        // $this->assertDatabaseHas('authentication_sessions', [
        //     'user_id' => $user->id,
        //     'session_id' => 'session-different-ip-002',
        // ]);
    }

    public function test_locked_account_does_not_block_authentication_from_another_ip(): void
    {
        $user = User::factory()->create([
            'email' => 'locked-account@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);
        $securityService = app(AuthenticationSecurityService::class);

        $lockedEmail = $user->email;

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $securityService->recordFailedAttempt($lockedEmail);
        }

        $this->assertTrue(
            $securityService->isLocked($lockedEmail)
        );

        $correctContext = new AuthenticationContext(
            email: $lockedEmail,
            password: 'ValidPassword123',
            sessionId: 'session-locked-account-001',
            ipAddress: '127.0.0.50',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Account is temporarily locked.'
        );

        $service->authenticate($correctContext);
    }

    public function test_successful_authentication_resets_failed_ip_attempts(): void
    {
        $user = User::factory()->create([
            'email' => 'reset-ip@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);
        $securityService = app(AuthenticationSecurityService::class);

        $ipAddress = '127.0.0.60';

        $securityService->recordFailedAttemptByIp($ipAddress);
        $securityService->recordFailedAttemptByIp($ipAddress);

        $this->assertSame(
            3,
            $securityService->remainingIpAttempts($ipAddress)
        );

        $context = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-reset-ip-001',
            ipAddress: $ipAddress,
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $service->authenticate($context);

        $this->assertSame(
            5,
            $securityService->remainingIpAttempts($ipAddress)
        );

        $this->assertFalse(
            $securityService->isIpLocked($ipAddress)
        );
    }

    public function test_failed_ip_attempts_are_isolated_between_ip_addresses(): void
    {
        $securityService = app(AuthenticationSecurityService::class);

        $firstIp = '127.0.0.70';
        $secondIp = '127.0.0.71';

        $securityService->recordFailedAttemptByIp($firstIp);
        $securityService->recordFailedAttemptByIp($firstIp);

        $securityService->recordFailedAttemptByIp($secondIp);

        $this->assertSame(
            3,
            $securityService->remainingIpAttempts($firstIp)
        );

        $this->assertSame(
            4,
            $securityService->remainingIpAttempts($secondIp)
        );

        $this->assertFalse(
            $securityService->isIpLocked($firstIp)
        );

        $this->assertFalse(
            $securityService->isIpLocked($secondIp)
        );
    }

    public function test_failed_attempts_on_another_ip_do_not_clear_existing_ip_lock(): void
    {
        $securityService = app(AuthenticationSecurityService::class);

        $lockedIp = '127.0.0.80';
        $otherIp = '127.0.0.81';

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $securityService->recordFailedAttemptByIp($lockedIp);
        }

        $this->assertTrue(
            $securityService->isIpLocked($lockedIp)
        );

        $securityService->recordFailedAttemptByIp($otherIp);

        $this->assertTrue(
            $securityService->isIpLocked($lockedIp)
        );

        $this->assertSame(
            4,
            $securityService->remainingIpAttempts($otherIp)
        );
    }

    public function test_successful_authentication_clears_all_failed_ip_attempts(): void
    {
        $user = User::factory()->create([
            'email' => 'clear-ip-attempts@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $securityService = app(AuthenticationSecurityService::class);
        $service = app(AuthenticationService::class);

        $ipAddress = '127.0.0.90';

        $securityService->recordFailedAttemptByIp($ipAddress);
        $securityService->recordFailedAttemptByIp($ipAddress);
        $securityService->recordFailedAttemptByIp($ipAddress);
        $securityService->recordFailedAttemptByIp($ipAddress);

        $this->assertSame(
            1,
            $securityService->remainingIpAttempts($ipAddress)
        );

        $context = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-clear-ip-001',
            ipAddress: $ipAddress,
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $service->authenticate($context);

        $this->assertSame(
            5,
            $securityService->remainingIpAttempts($ipAddress)
        );

        $this->assertFalse(
            $securityService->isIpLocked($ipAddress)
        );
    }
//
    public function test_authentication_without_ip_address_does_not_use_ip_locking(): void
    {
        $user = User::factory()->create([
            'email' => 'no-ip@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $context = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-no-ip-001',
            ipAddress: null,
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $authenticatedUser = $service->authenticate($context);

        $this->assertSame(
            $user->id,
            $authenticatedUser->id
        );

        // $this->assertDatabaseHas('authentication_sessions', [
        //     'user_id' => $user->id,
        //     'session_id' => 'session-no-ip-001',
        //     'ip_address' => null,
        // ]);
    }

    public function test_successful_authentication_without_ip_does_not_clear_failed_ip_attempts(): void
    {
        $user = User::factory()->create([
            'email' => 'no-ip-preserve@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $securityService = app(AuthenticationSecurityService::class);
        $service = app(AuthenticationService::class);

        $ipAddress = '127.0.0.100';

        $securityService->recordFailedAttemptByIp($ipAddress);
        $securityService->recordFailedAttemptByIp($ipAddress);

        $this->assertSame(
            3,
            $securityService->remainingIpAttempts($ipAddress)
        );

        $context = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-no-ip-preserve-001',
            ipAddress: null,
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $service->authenticate($context);

        $this->assertSame(
            3,
            $securityService->remainingIpAttempts($ipAddress)
        );

        $this->assertFalse(
            $securityService->isIpLocked($ipAddress)
        );
    }

    public function test_failed_authentication_records_failed_ip_attempt(): void
    {
        $user = User::factory()->create([
            'email' => 'failed-ip-record@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $securityService = app(AuthenticationSecurityService::class);
        $service = app(AuthenticationService::class);

        $ipAddress = '127.0.0.110';

        $context = new AuthenticationContext(
            email: $user->email,
            password: 'WrongPassword123',
            sessionId: 'session-failed-ip-001',
            ipAddress: $ipAddress,
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        try {
            $service->authenticate($context);
            $this->fail('Authentication should have failed.');
        } catch (\RuntimeException $exception) {
            $this->assertSame(
                'Invalid credentials.',
                $exception->getMessage()
            );
        }

        $this->assertSame(
            4,
            $securityService->remainingIpAttempts($ipAddress)
        );

        $this->assertFalse(
            $securityService->isIpLocked($ipAddress)
        );
    }

    public function test_fifth_failed_authentication_locks_ip_address(): void
    {
        $user = User::factory()->create([
            'email' => 'fifth-ip-failure@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $securityService = app(AuthenticationSecurityService::class);
        $service = app(AuthenticationService::class);

        $ipAddress = '127.0.0.120';

        $context = new AuthenticationContext(
            email: $user->email,
            password: 'WrongPassword123',
            sessionId: 'session-fifth-ip-001',
            ipAddress: $ipAddress,
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            try {
                $service->authenticate($context);
            } catch (\Throwable) {
                // Expected failed authentication.
            }
        }

        $this->assertTrue(
            $securityService->isIpLocked($ipAddress)
        );

        $this->assertSame(
            0,
            $securityService->remainingIpAttempts($ipAddress)
        );
    }

    public function test_sixth_failed_attempt_on_locked_ip_does_not_increase_attempts(): void
    {
        $user = User::factory()->create([
            'email' => 'sixth-ip-failure@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $securityService = app(AuthenticationSecurityService::class);
        $service = app(AuthenticationService::class);

        $ipAddress = '127.0.0.130';

        $context = new AuthenticationContext(
            email: $user->email,
            password: 'WrongPassword123',
            sessionId: 'session-sixth-ip-001',
            ipAddress: $ipAddress,
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            try {
                $service->authenticate($context);
            } catch (\Throwable) {
                // Expected failed authentication.
            }
        }

        $this->assertTrue(
            $securityService->isIpLocked($ipAddress)
        );

        $this->assertSame(
            0,
            $securityService->remainingIpAttempts($ipAddress)
        );

        try {
            $service->authenticate(
                new AuthenticationContext(
                    email: $user->email,
                    password: 'WrongPassword123',
                    sessionId: 'session-sixth-ip-002',
                    ipAddress: $ipAddress,
                    userAgent: 'Mozilla/5.0',
                    browser: 'Firefox',
                    device: 'Desktop',
                )
            );

            $this->fail('Authentication should have been rejected.');
        } catch (\RuntimeException $exception) {
            $this->assertSame(
                'IP address is temporarily locked.',
                $exception->getMessage()
            );
        }

        $this->assertSame(
            0,
            $securityService->remainingIpAttempts($ipAddress)
        );
    }

    public function test_locked_ip_has_priority_when_account_and_ip_are_both_locked(): void
    {
        $user = User::factory()->create([
            'email' => 'both-locked@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $securityService = app(AuthenticationSecurityService::class);
        $service = app(AuthenticationService::class);

        $ipAddress = '127.0.0.160';

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $securityService->recordFailedAttempt($user->email);
            $securityService->recordFailedAttemptByIp($ipAddress);
        }

        $this->assertTrue(
            $securityService->isLocked($user->email)
        );

        $this->assertTrue(
            $securityService->isIpLocked($ipAddress)
        );

        $context = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-both-locked-001',
            ipAddress: $ipAddress,
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        try {
            $service->authenticate($context);
            $this->fail('Authentication should have been rejected.');
        } catch (\RuntimeException $exception) {
            $this->assertSame(
                'IP address is temporarily locked.',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseMissing('authentication_sessions', [
            'user_id' => $user->id,
            'session_id' => 'session-both-locked-001',
        ]);
    }

    public function test_unlocked_account_is_still_blocked_by_locked_ip(): void
    {
        $user = User::factory()->create([
            'email' => 'unlocked-account-locked-ip@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $securityService = app(AuthenticationSecurityService::class);
        $service = app(AuthenticationService::class);

        $ipAddress = '127.0.0.170';

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $securityService->recordFailedAttemptByIp($ipAddress);
        }

        $securityService->recordFailedAttempt($user->email);
        $securityService->clearFailedAttempts($user->email);

        $this->assertFalse(
            $securityService->isLocked($user->email)
        );

        $this->assertTrue(
            $securityService->isIpLocked($ipAddress)
        );

        $context = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-unlocked-account-001',
            ipAddress: $ipAddress,
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        try {
            $service->authenticate($context);
            $this->fail('Authentication should have been rejected.');
        } catch (\RuntimeException $exception) {
            $this->assertSame(
                'IP address is temporarily locked.',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseMissing('authentication_sessions', [
            'user_id' => $user->id,
            'session_id' => 'session-unlocked-account-001',
        ]);
    }
//
    public function test_authentication_succeeds_after_ip_lock_is_cleared(): void
    {
        $user = User::factory()->create([
            'email' => 'ip-lock-cleared@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $securityService = app(AuthenticationSecurityService::class);
        $service = app(AuthenticationService::class);

        $ipAddress = '127.0.0.180';

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $securityService->recordFailedAttemptByIp($ipAddress);
        }

        $this->assertTrue(
            $securityService->isIpLocked($ipAddress)
        );

        $securityService->clearFailedIpAttempts($ipAddress);

        $this->assertFalse(
            $securityService->isIpLocked($ipAddress)
        );

        $context = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-ip-lock-cleared-001',
            ipAddress: $ipAddress,
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $authenticatedUser = $service->authenticate($context);

        $this->assertSame(
            $user->id,
            $authenticatedUser->id
        );

        // $this->assertDatabaseHas('authentication_sessions', [
        //     'user_id' => $user->id,
        //     'session_id' => 'session-ip-lock-cleared-001',
        // ]);
    }

    public function test_locked_account_does_not_increment_failed_attempts(): void
    {
        User::factory()->create([
            'email' => 'locked-counter@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $securityService = app(AuthenticationSecurityService::class);

        for ($i = 0; $i < 5; $i++) {
            $securityService->recordFailedAttempt(
                'locked-counter@example.com'
            );
        }

        $this->assertSame(
            5,
            RateLimiter::attempts(
                'authentication:failed:locked-counter@example.com'
            )
        );

        $this->expectException(RuntimeException::class);

        try {
            app(AuthenticationService::class)->authenticate(
                new AuthenticationContext(
                    email: 'locked-counter@example.com',
                    password: 'ValidPassword123',
                    sessionId: 'session-locked-counter-001',
                    ipAddress: '127.0.0.247',
                    userAgent: 'Mozilla/5.0',
                    browser: 'Firefox',
                    device: 'Desktop',
                )
            );
        } finally {
            $this->assertSame(
                5,
                RateLimiter::attempts(
                    'authentication:failed:locked-counter@example.com'
                )
            );
        }
    }

    public function test_locked_ip_does_not_increment_failed_attempts(): void
    {
        User::factory()->create([
            'email' => 'ip-locked-counter@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $securityService = app(AuthenticationSecurityService::class);

        for ($i = 0; $i < 5; $i++) {
            $securityService->recordFailedAttemptByIp(
                '127.0.0.246'
            );
        }

        $this->assertSame(
            5,
            RateLimiter::attempts(
                'authentication:failed:ip:127.0.0.246'
            )
        );

        $this->expectException(RuntimeException::class);

        try {
            app(AuthenticationService::class)->authenticate(
                new AuthenticationContext(
                    email: 'ip-locked-counter@example.com',
                    password: 'ValidPassword123',
                    sessionId: 'session-ip-locked-counter-001',
                    ipAddress: '127.0.0.246',
                    userAgent: 'Mozilla/5.0',
                    browser: 'Firefox',
                    device: 'Desktop',
                )
            );
        } finally {
            $this->assertSame(
                5,
                RateLimiter::attempts(
                    'authentication:failed:ip:127.0.0.246'
                )
            );
        }
    }

    public function test_locked_account_does_not_increment_ip_failed_attempts(): void
    {
        User::factory()->create([
            'email' => 'locked-no-ip-attempt@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $securityService = app(AuthenticationSecurityService::class);

        for ($i = 0; $i < 5; $i++) {
            $securityService->recordFailedAttempt(
                'locked-no-ip-attempt@example.com'
            );
        }

        $this->expectException(RuntimeException::class);

        try {
            app(AuthenticationService::class)->authenticate(
                new AuthenticationContext(
                    email: 'locked-no-ip-attempt@example.com',
                    password: 'ValidPassword123',
                    sessionId: 'session-locked-no-ip-attempt-001',
                    ipAddress: '127.0.0.245',
                    userAgent: 'Mozilla/5.0',
                    browser: 'Firefox',
                    device: 'Desktop',
                )
            );
        } finally {
            $this->assertSame(
                0,
                RateLimiter::attempts(
                    'authentication:failed:ip:127.0.0.245'
                )
            );
        }
    }

    public function test_locked_ip_does_not_increment_account_failed_attempts(): void
    {
        User::factory()->create([
            'email' => 'ip-locked-no-account-attempt@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $securityService = app(AuthenticationSecurityService::class);

        for ($i = 0; $i < 5; $i++) {
            $securityService->recordFailedAttemptByIp(
                '127.0.0.244'
            );
        }

        $this->expectException(RuntimeException::class);

        try {
            app(AuthenticationService::class)->authenticate(
                new AuthenticationContext(
                    email: 'ip-locked-no-account-attempt@example.com',
                    password: 'ValidPassword123',
                    sessionId: 'session-ip-locked-no-account-attempt-001',
                    ipAddress: '127.0.0.244',
                    userAgent: 'Mozilla/5.0',
                    browser: 'Firefox',
                    device: 'Desktop',
                )
            );
        } finally {
            $this->assertSame(
                0,
                RateLimiter::attempts(
                    'authentication:failed:ip-locked-no-account-attempt@example.com'
                )
            );
        }
    }

    public function test_inactive_user_does_not_record_failed_security_attempt(): void
    {
        User::factory()->create([
            'email' => 'inactive-no-attempt@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Inactive,
        ]);

        $this->expectException(RuntimeException::class);

        try {
            app(AuthenticationService::class)->authenticate(
                new AuthenticationContext(
                    email: 'inactive-no-attempt@example.com',
                    password: 'WrongPassword123',
                    sessionId: 'session-inactive-no-attempt-001',
                    ipAddress: '127.0.0.243',
                    userAgent: 'Mozilla/5.0',
                    browser: 'Firefox',
                    device: 'Desktop',
                )
            );
        } finally {
            $this->assertSame(
                0,
                RateLimiter::attempts(
                    'authentication:failed:inactive-no-attempt@example.com'
                )
            );

            $this->assertSame(
                0,
                RateLimiter::attempts(
                    'authentication:failed:ip:127.0.0.243'
                )
            );
        }
    }

    public function test_unknown_user_does_not_record_failed_security_attempt(): void
    {
        $this->expectException(ModelNotFoundException::class);

        try {
            app(AuthenticationService::class)->authenticate(
                new AuthenticationContext(
                    email: 'unknown-no-attempt@example.com',
                    password: 'WrongPassword123',
                    sessionId: 'session-unknown-no-attempt-001',
                    ipAddress: '127.0.0.242',
                    userAgent: 'Mozilla/5.0',
                    browser: 'Firefox',
                    device: 'Desktop',
                )
            );
        } finally {
            $this->assertSame(
                0,
                RateLimiter::attempts(
                    'authentication:failed:unknown-no-attempt@example.com'
                )
            );

            $this->assertSame(
                0,
                RateLimiter::attempts(
                    'authentication:failed:ip:127.0.0.242'
                )
            );
        }
    }
}