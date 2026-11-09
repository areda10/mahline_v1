<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Authentication\Services;

use App\Domains\Identity\Authentication\DTOs\AuthenticationContext;
use App\Domains\Identity\Authentication\Exceptions\AuthenticationFailedException;
use App\Domains\Identity\Authentication\Services\AuthenticationSecurityService;
use App\Domains\Identity\Authentication\Services\AuthenticationService;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class AuthenticationServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuthenticationService $authenticationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authenticationService = app(
            AuthenticationService::class
        );
    }

    public function test_authenticates_user_successfully(): void
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => 'password',
            'status' => UserStatus::Active,
        ]);

        $context = new AuthenticationContext(
            email: 'john@example.com',
            password: 'password',
            sessionId: 'session-success',
            ipAddress: '127.0.0.1',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $authenticatedUser = $this->authenticationService->authenticate(
            $context
        );

        $this->assertSame(
            $user->getKey(),
            $authenticatedUser->getKey()
        );

        $this->assertDatabaseMissing('login_histories', [
            'user_id' => $user->getKey(),
            'event' => 'success',
        ]);
    }

    public function test_unknown_user_is_rejected(): void
    {
        $context = new AuthenticationContext(
            email: 'unknown@example.com',
            password: 'password',
            sessionId: 'session-unknown-user',
            ipAddress: '127.0.0.1',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $this->expectException(AuthenticationFailedException::class);

        $this->authenticationService->authenticate($context);
    }

    public function test_unknown_user_is_recorded_as_failed_authentication(): void
    {
        $context = new AuthenticationContext(
            email: '  UNKNOWN@EXAMPLE.COM  ',
            password: 'password',
            sessionId: 'session-unknown-history',
            ipAddress: '127.0.0.2',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        try {
            $this->authenticationService->authenticate($context);
        } catch (AuthenticationFailedException) {
            // Expected.
        }

        $this->assertDatabaseHas('login_histories', [
            'email' => 'unknown@example.com',
            'event' => 'failed',
        ]);
    }

    public function test_active_user_with_unverified_email_is_rejected(): void
    {
        $user = User::factory()->create([
            'email' => 'unverified@example.com',
            'password' => 'password',
            'status' => UserStatus::Active,
            'email_verified_at' => null,
        ]);

        $context = new AuthenticationContext(
            email: 'unverified@example.com',
            password: 'password',
            sessionId: 'session-unverified',
            ipAddress: '127.0.0.30',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $this->expectException(AuthenticationFailedException::class);
        $this->expectExceptionMessage('Email address is not verified.');

        try {
            $this->authenticationService->authenticate($context);
        } finally {
            $this->assertDatabaseHas('login_histories', [
                'user_id' => $user->getKey(),
                'event' => 'failed',
                'reason' => 'email_not_verified',
            ]);
        }
    }

    public function test_inactive_user_is_rejected(): void
    {
        $user = User::factory()->create([
            'email' => 'inactive@example.com',
            'password' => 'password',
            'status' => UserStatus::Inactive,
        ]);

        $context = new AuthenticationContext(
            email: 'inactive@example.com',
            password: 'password',
            sessionId: 'session-inactive',
            ipAddress: '127.0.0.3',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $this->expectException(AuthenticationFailedException::class);
        $this->expectExceptionMessage('User account is not active.');

        $this->authenticationService->authenticate($context);
    }

    public function test_inactive_user_is_recorded_in_login_history(): void
    {
        $user = User::factory()->create([
            'email' => 'inactive-history@example.com',
            'password' => 'password',
            'status' => UserStatus::Inactive,
        ]);

        $context = new AuthenticationContext(
            email: 'inactive-history@example.com',
            password: 'password',
            sessionId: 'session-inactive-history',
            ipAddress: '127.0.0.4',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        try {
            $this->authenticationService->authenticate($context);
        } catch (AuthenticationFailedException) {
            // Expected.
        }

        $this->assertDatabaseHas('login_histories', [
            'user_id' => $user->getKey(),
            'email' => 'inactive-history@example.com',
            'event' => 'failed',
            'reason' => 'account_not_active',
        ]);
    }

    public function test_invalid_password_is_rejected(): void
    {
        User::factory()->create([
            'email' => 'john@example.com',
            'password' => 'correct-password',
            'status' => UserStatus::Active,
        ]);

        $context = new AuthenticationContext(
            email: 'john@example.com',
            password: 'wrong-password',
            sessionId: 'session-invalid-password',
            ipAddress: '127.0.0.5',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $this->expectException(AuthenticationFailedException::class);
        $this->expectExceptionMessage('Invalid credentials.');

        $this->authenticationService->authenticate($context);
    }

    public function test_invalid_password_records_failed_authentication(): void
    {
        $user = User::factory()->create([
            'email' => 'invalid-password-history@example.com',
            'password' => 'correct-password',
            'status' => UserStatus::Active,
        ]);

        $context = new AuthenticationContext(
            email: 'invalid-password-history@example.com',
            password: 'wrong-password',
            sessionId: 'session-invalid-password-history',
            ipAddress: '127.0.0.6',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        try {
            $this->authenticationService->authenticate($context);
        } catch (AuthenticationFailedException) {
            // Expected.
        }

        $this->assertDatabaseHas('login_histories', [
            'user_id' => $user->getKey(),
            'email' => $user->email,
            'event' => 'failed',
            'reason' => 'invalid_credentials',
        ]);
    }

    public function test_authentication_accepts_email_with_different_case_and_surrounding_spaces(): void
    {
        $user = User::factory()->create([
            'email' => 'normalized-email@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $authenticatedUser = $this->authenticationService->authenticate(
            new AuthenticationContext(
                email: '  NORMALIZED-EMAIL@EXAMPLE.COM  ',
                password: 'ValidPassword123',
                sessionId: 'session-normalized-email-001',
                ipAddress: '127.0.0.7',
                userAgent: 'Mozilla/5.0',
                browser: 'Firefox',
                device: 'Desktop',
            )
        );

        $this->assertSame(
            $user->getKey(),
            $authenticatedUser->getKey()
        );
    }

    public function test_authentication_rejects_email_containing_only_spaces(): void
    {
        $context = new AuthenticationContext(
            email: '     ',
            password: 'ValidPassword123',
            sessionId: 'session-empty-email-001',
            ipAddress: '127.0.0.8',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $this->expectException(AuthenticationFailedException::class);

        $this->authenticationService->authenticate($context);
    }

    public function test_authentication_rejects_email_with_internal_spaces(): void
    {
        User::factory()->create([
            'email' => 'valid-user@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $context = new AuthenticationContext(
            email: 'valid user@example.com',
            password: 'ValidPassword123',
            sessionId: 'session-internal-space-email-001',
            ipAddress: '127.0.0.9',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $this->expectException(AuthenticationFailedException::class);

        $this->authenticationService->authenticate($context);
    }

    public function test_locked_account_rejects_authentication(): void
    {
        User::factory()->create([
            'email' => 'locked-account@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $securityService = app(AuthenticationSecurityService::class);

        for ($i = 0; $i < 5; $i++) {
            $securityService->recordFailedAttempt(
                'locked-account@example.com'
            );
        }

        $context = new AuthenticationContext(
            email: 'locked-account@example.com',
            password: 'ValidPassword123',
            sessionId: 'session-locked-account',
            ipAddress: '127.0.0.10',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $this->expectException(AuthenticationFailedException::class);

        $this->authenticationService->authenticate($context);
    }

    public function test_locked_account_records_normalized_email_in_login_history(): void
    {
        User::factory()->create([
            'email' => 'locked-normalized@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $securityService = app(AuthenticationSecurityService::class);

        for ($i = 0; $i < 5; $i++) {
            $securityService->recordFailedAttempt(
                'locked-normalized@example.com'
            );
        }

        $context = new AuthenticationContext(
            email: '  LOCKED-NORMALIZED@EXAMPLE.COM  ',
            password: 'ValidPassword123',
            sessionId: 'session-normalized-locked-001',
            ipAddress: '127.0.0.11',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        try {
            $this->authenticationService->authenticate($context);
        } catch (AuthenticationFailedException) {
            // Expected.
        }

        $this->assertDatabaseHas('login_histories', [
            'email' => 'locked-normalized@example.com',
            'event' => 'failed',
        ]);
    }

    public function test_locked_account_does_not_increment_failed_attempts(): void
    {
        User::factory()->create([
            'email' => 'locked-account-counter@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $securityService = app(AuthenticationSecurityService::class);

        for ($i = 0; $i < 5; $i++) {
            $securityService->recordFailedAttempt(
                'locked-account-counter@example.com'
            );
        }

        $this->assertSame(
            0,
            $securityService->remainingAttempts(
                'locked-account-counter@example.com'
            )
        );

        try {
            $this->authenticationService->authenticate(
                new AuthenticationContext(
                    email: 'locked-account-counter@example.com',
                    password: 'ValidPassword123',
                    sessionId: 'session-locked-account-counter',
                    ipAddress: '127.0.0.12',
                    userAgent: 'Mozilla/5.0',
                    browser: 'Firefox',
                    device: 'Desktop',
                )
            );
        } catch (AuthenticationFailedException) {
            // Expected.
        }

        $this->assertSame(
            0,
            $securityService->remainingAttempts(
                'locked-account-counter@example.com'
            )
        );
    }

    public function test_locked_ip_rejects_authentication(): void
    {
        User::factory()->create([
            'email' => 'ip-locked@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $securityService = app(AuthenticationSecurityService::class);

        for ($i = 0; $i < 30; $i++) {
            $securityService->recordFailedAttemptByIp('127.0.0.13');
        }

        $context = new AuthenticationContext(
            email: 'ip-locked@example.com',
            password: 'ValidPassword123',
            sessionId: 'session-locked-ip',
            ipAddress: '127.0.0.13',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $this->expectException(AuthenticationFailedException::class);

        $this->authenticationService->authenticate($context);
    }

    public function test_locked_ip_records_normalized_email_in_login_history(): void
    {
        User::factory()->create([
            'email' => 'ip-locked-normalized@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $securityService = app(AuthenticationSecurityService::class);

        for ($i = 0; $i < 30; $i++) {
            $securityService->recordFailedAttemptByIp('127.0.0.14');
        }

        $context = new AuthenticationContext(
            email: '  IP-LOCKED-NORMALIZED@EXAMPLE.COM  ',
            password: 'ValidPassword123',
            sessionId: 'session-normalized-ip-locked-001',
            ipAddress: '127.0.0.14',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        try {
            $this->authenticationService->authenticate($context);
        } catch (AuthenticationFailedException) {
            // Expected.
        }

        $this->assertDatabaseHas('login_histories', [
            'email' => 'ip-locked-normalized@example.com',
            'event' => 'failed',
            'reason' => 'ip_locked',
        ]);
    }

    public function test_locked_ip_does_not_increment_failed_attempts(): void
    {
        User::factory()->create([
            'email' => 'ip-locked-counter@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $securityService = app(AuthenticationSecurityService::class);

        for ($i = 0; $i < 30; $i++) {
            $securityService->recordFailedAttemptByIp('127.0.0.15');
        }

        $this->assertSame(
            0,
            $securityService->remainingIpAttempts('127.0.0.15')
        );

        try {
            $this->authenticationService->authenticate(
                new AuthenticationContext(
                    email: 'ip-locked-counter@example.com',
                    password: 'ValidPassword123',
                    sessionId: 'session-locked-ip-counter',
                    ipAddress: '127.0.0.15',
                    userAgent: 'Mozilla/5.0',
                    browser: 'Firefox',
                    device: 'Desktop',
                )
            );
        } catch (AuthenticationFailedException) {
            // Expected.
        }

        $this->assertSame(
            0,
            $securityService->remainingIpAttempts('127.0.0.15')
        );
    }

    public function test_locked_ip_has_priority_over_locked_account_in_login_history(): void
    {
        User::factory()->create([
            'email' => 'priority-normalized@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $securityService = app(AuthenticationSecurityService::class);

        for ($i = 0; $i < 5; $i++) {
            $securityService->recordFailedAttempt(
                'priority-normalized@example.com'
            );
        }

        for ($i = 0; $i < 30; $i++) {
            $securityService->recordFailedAttemptByIp('127.0.0.16');
        }

        $context = new AuthenticationContext(
            email: '  PRIORITY-NORMALIZED@EXAMPLE.COM  ',
            password: 'ValidPassword123',
            sessionId: 'session-priority-locked-001',
            ipAddress: '127.0.0.16',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        try {
            $this->authenticationService->authenticate($context);
        } catch (AuthenticationFailedException) {
            // Expected.
        }

        $this->assertDatabaseHas('login_histories', [
            'email' => 'priority-normalized@example.com',
            'event' => 'failed',
            'reason' => 'ip_locked',
        ]);

        $this->assertDatabaseMissing('login_histories', [
            'email' => 'priority-normalized@example.com',
            'reason' => 'account_locked',
        ]);
    }

    public function test_successful_authentication_clears_failed_attempts_using_normalized_email(): void
    {
        $user = User::factory()->create([
            'email' => 'security-normalized@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $securityService = app(AuthenticationSecurityService::class);

        $securityService->recordFailedAttempt($user->email);

        $this->assertSame(
            4,
            $securityService->remainingAttempts($user->email)
        );

        $this->authenticationService->authenticate(
            new AuthenticationContext(
                email: '  SECURITY-NORMALIZED@EXAMPLE.COM  ',
                password: 'ValidPassword123',
                sessionId: 'session-security-normalized-001',
                ipAddress: '127.0.0.17',
                userAgent: 'Mozilla/5.0',
                browser: 'Firefox',
                device: 'Desktop',
            )
        );

        $this->assertSame(
            5,
            $securityService->remainingAttempts($user->email)
        );
    }

    public function test_successful_login_rehashes_an_outdated_password_hash(): void
    {
        $user = User::factory()->create([
            'email' => 'legacy-hash@example.com',
            'password' => 'LegacyPassword2026!',
            'status' => UserStatus::Active,
        ]);

        $legacyHash = password_hash(
            'LegacyPassword2026!',
            PASSWORD_BCRYPT,
            ['cost' => 4],
        );

        $this->assertIsString($legacyHash);
        $this->assertTrue(Hash::check('LegacyPassword2026!', $legacyHash));
        $this->assertTrue(Hash::needsRehash($legacyHash));

        $user->forceFill(['password' => $legacyHash])->save();
        $user->refresh();

        $this->authenticationService->authenticate(
            new AuthenticationContext(
                email: $user->email,
                password: 'LegacyPassword2026!',
                sessionId: 'session-legacy-hash-001',
                ipAddress: '127.0.0.20',
                userAgent: 'Mozilla/5.0',
                browser: 'Firefox',
                device: 'Desktop',
            )
        );

        $user->refresh();

        $this->assertNotSame($legacyHash, $user->password);
        $this->assertTrue(Hash::check('LegacyPassword2026!', (string) $user->password));
        $this->assertFalse(Hash::needsRehash((string) $user->password));
        $this->assertDatabaseMissing('user_password_histories', [
            'user_id' => $user->getKey(),
        ]);
    }

    public function test_successful_login_does_not_rehash_when_rehash_on_login_is_disabled(): void
    {
        config(['hashing.rehash_on_login' => false]);

        $user = User::factory()->create([
            'email' => 'rehash-disabled@example.com',
            'password' => 'LegacyPassword2026!',
            'status' => UserStatus::Active,
        ]);

        $legacyHash = password_hash(
            'LegacyPassword2026!',
            PASSWORD_BCRYPT,
            ['cost' => 4],
        );

        $this->assertIsString($legacyHash);

        $user->forceFill(['password' => $legacyHash])->save();
        $user->refresh();

        $this->authenticationService->authenticate(
            new AuthenticationContext(
                email: $user->email,
                password: 'LegacyPassword2026!',
                sessionId: 'session-rehash-disabled-001',
                ipAddress: '127.0.0.21',
                userAgent: 'Mozilla/5.0',
                browser: 'Firefox',
                device: 'Desktop',
            )
        );

        $this->assertSame($legacyHash, $user->fresh()->password);
    }
    public function test_failed_attempts_are_counted_for_unknown_inactive_and_unverified_emails(): void
    {
        $inactive = User::factory()->create([
            'email' => 'inactive-counter@example.com',
            'password' => Hash::make('ValidPassword123!'),
            'status' => UserStatus::Inactive,
            'email_verified_at' => now(),
        ]);

        $unverified = User::factory()->create([
            'email' => 'unverified-counter@example.com',
            'password' => Hash::make('ValidPassword123!'),
            'status' => UserStatus::Active,
            'email_verified_at' => null,
        ]);

        $securityService = app(AuthenticationSecurityService::class);

        $cases = [
            'unknown-counter@example.com',
            $inactive->email,
            $unverified->email,
        ];

        foreach ($cases as $email) {
            for ($attempt = 0; $attempt < 5; $attempt++) {
                try {
                    $this->authenticationService->authenticate(
                        new AuthenticationContext(
                            email: $email,
                            password: 'ValidPassword123!',
                            sessionId: 'failed-counter-'.$attempt.'-'.$email,
                            ipAddress: null,
                            userAgent: 'Mozilla/5.0',
                            browser: 'Firefox',
                            device: 'Desktop',
                        )
                    );
                } catch (\Throwable) {
                    // Expected: every rejected attempt is still counted.
                }
            }

            $this->assertSame(
                0,
                $securityService->remainingAttempts($email),
                'The email counter must reach the lockout threshold for '.$email,
            );

            $this->assertTrue(
                $securityService->isLocked($email),
                'The email must be locked after five failed attempts: '.$email,
            );
        }
    }

    public function test_locked_unknown_email_does_not_increment_failed_attempts(): void
    {
        $email = 'already-locked-unknown@example.com';
        $securityService = app(AuthenticationSecurityService::class);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $securityService->recordFailedAttempt($email);
        }

        try {
            $this->authenticationService->authenticate(
                new AuthenticationContext(
                    email: $email,
                    password: 'ValidPassword123!',
                    sessionId: 'locked-unknown-email',
                    ipAddress: null,
                    userAgent: 'Mozilla/5.0',
                    browser: 'Firefox',
                    device: 'Desktop',
                )
            );
        } catch (\Throwable) {
            // Expected: an already locked email is rejected without increment.
        }

        $this->assertSame(0, $securityService->remainingAttempts($email));

        $this->assertDatabaseHas('login_histories', [
            'email' => $email,
            'event' => 'failed',
            'reason' => 'account_locked',
        ]);
    }

    public function test_all_processed_failure_types_increment_ip_and_ip_email_counters(): void
    {
        $inactive = User::factory()->create([
            'email' => 'inactive-ip-counter@example.com',
            'password' => Hash::make('ValidPassword123!'),
            'status' => UserStatus::Inactive,
            'email_verified_at' => now(),
        ]);

        $unverified = User::factory()->create([
            'email' => 'unverified-ip-counter@example.com',
            'password' => Hash::make('ValidPassword123!'),
            'status' => UserStatus::Active,
            'email_verified_at' => null,
        ]);

        $invalidPassword = User::factory()->create([
            'email' => 'wrong-password-ip-counter@example.com',
            'password' => Hash::make('ValidPassword123!'),
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
        ]);

        $ipAddress = '198.51.100.41';
        $securityService = app(AuthenticationSecurityService::class);

        $attempts = [
            ['unknown-ip-counter@example.com', 'ValidPassword123!'],
            [$inactive->email, 'ValidPassword123!'],
            [$unverified->email, 'ValidPassword123!'],
            [$invalidPassword->email, 'WrongPassword123!'],
        ];

        foreach ($attempts as $index => [$email, $password]) {
            try {
                $this->authenticationService->authenticate(
                    new AuthenticationContext(
                        email: $email,
                        password: $password,
                        sessionId: 'all-failure-types-'.$index,
                        ipAddress: $ipAddress,
                        userAgent: 'Mozilla/5.0',
                        browser: 'Firefox',
                        device: 'Desktop',
                    )
                );
            } catch (\Throwable) {
                // Every case is an expected authentication failure.
            }

            $this->assertSame(
                4,
                $securityService->remainingIpEmailAttempts($ipAddress, $email),
            );
        }

        $this->assertSame(26, $securityService->remainingIpAttempts($ipAddress));
    }

    public function test_ip_email_counter_is_cleared_on_success_without_clearing_shared_ip_counter(): void
    {
        $user = User::factory()->create([
            'email' => 'pair-clear@example.com',
            'password' => Hash::make('ValidPassword123!'),
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
        ]);

        $ipAddress = '198.51.100.42';
        $securityService = app(AuthenticationSecurityService::class);

        $securityService->recordFailedAuthentication($user->email, $ipAddress);
        $securityService->recordFailedAuthentication($user->email, $ipAddress);

        $this->assertSame(3, $securityService->remainingAttempts($user->email));
        $this->assertSame(3, $securityService->remainingIpEmailAttempts($ipAddress, $user->email));
        $this->assertSame(28, $securityService->remainingIpAttempts($ipAddress));

        $this->authenticationService->authenticate(
            new AuthenticationContext(
                email: $user->email,
                password: 'ValidPassword123!',
                sessionId: 'pair-clear-success',
                ipAddress: $ipAddress,
                userAgent: 'Mozilla/5.0',
                browser: 'Firefox',
                device: 'Desktop',
            )
        );

        $this->assertSame(5, $securityService->remainingAttempts($user->email));
        $this->assertSame(5, $securityService->remainingIpEmailAttempts($ipAddress, $user->email));
        $this->assertSame(28, $securityService->remainingIpAttempts($ipAddress));
    }

    public function test_ip_lockout_threshold_is_thirty_failures(): void
    {
        $securityService = app(AuthenticationSecurityService::class);
        $ipAddress = '198.51.100.43';

        for ($attempt = 1; $attempt <= 29; $attempt++) {
            $securityService->recordFailedAttemptByIp($ipAddress);

            $this->assertFalse($securityService->isIpLocked($ipAddress));
        }

        $this->assertSame(1, $securityService->remainingIpAttempts($ipAddress));

        $securityService->recordFailedAttemptByIp($ipAddress);

        $this->assertTrue($securityService->isIpLocked($ipAddress));
        $this->assertSame(0, $securityService->remainingIpAttempts($ipAddress));
    }

    public function test_ip_email_lockout_threshold_is_five_failures(): void
    {
        $securityService = app(AuthenticationSecurityService::class);
        $ipAddress = '198.51.100.44';
        $email = 'pair-lock@example.com';

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $securityService->recordFailedAttemptByIpAndEmail($ipAddress, $email);

            $this->assertFalse(
                $securityService->isIpEmailLocked($ipAddress, $email),
            );
        }

        $this->assertSame(
            1,
            $securityService->remainingIpEmailAttempts($ipAddress, $email),
        );

        $securityService->recordFailedAttemptByIpAndEmail($ipAddress, $email);

        $this->assertTrue(
            $securityService->isIpEmailLocked($ipAddress, $email),
        );
        $this->assertSame(
            0,
            $securityService->remainingIpEmailAttempts($ipAddress, $email),
        );
    }

    public function test_all_three_lockouts_expire_after_fifteen_minutes(): void
    {
        $securityService = app(AuthenticationSecurityService::class);
        $ipAddress = '198.51.100.45';
        $email = 'lockout-expiration@example.com';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $securityService->recordFailedAttempt($email);
            $securityService->recordFailedAttemptByIpAndEmail($ipAddress, $email);
        }

        for ($attempt = 0; $attempt < 30; $attempt++) {
            $securityService->recordFailedAttemptByIp($ipAddress);
        }

        $this->assertTrue($securityService->isLocked($email));
        $this->assertTrue($securityService->isIpLocked($ipAddress));
        $this->assertTrue($securityService->isIpEmailLocked($ipAddress, $email));

        $this->travel(16)->minutes();

        $this->assertFalse($securityService->isLocked($email));
        $this->assertFalse($securityService->isIpLocked($ipAddress));
        $this->assertFalse($securityService->isIpEmailLocked($ipAddress, $email));
        $this->assertSame(5, $securityService->remainingAttempts($email));
        $this->assertSame(30, $securityService->remainingIpAttempts($ipAddress));
        $this->assertSame(5, $securityService->remainingIpEmailAttempts($ipAddress, $email));
    }

    public function test_locked_ip_email_pair_is_rejected_without_incrementing_its_counter_again(): void
    {
        $user = User::factory()->create([
            'email' => 'locked-pair@example.com',
            'password' => Hash::make('ValidPassword123!'),
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
        ]);

        $ipAddress = '198.51.100.46';
        $securityService = app(AuthenticationSecurityService::class);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $securityService->recordFailedAttemptByIpAndEmail(
                $ipAddress,
                $user->email,
            );
        }

        try {
            $this->authenticationService->authenticate(
                new AuthenticationContext(
                    email: $user->email,
                    password: 'ValidPassword123!',
                    sessionId: 'locked-pair-no-increment',
                    ipAddress: $ipAddress,
                    userAgent: 'Mozilla/5.0',
                    browser: 'Firefox',
                    device: 'Desktop',
                )
            );
        } catch (AuthenticationFailedException) {
            // Expected: the IP + email pair is already locked.
        }

        $this->assertSame(
            0,
            $securityService->remainingIpEmailAttempts($ipAddress, $user->email),
        );

        $this->assertSame(
            5,
            $securityService->remainingAttempts($user->email),
        );

        $this->assertSame(
            30,
            $securityService->remainingIpAttempts($ipAddress),
        );

        $this->assertDatabaseHas('login_histories', [
            'email' => $user->email,
            'event' => 'failed',
            'reason' => 'ip_email_locked',
        ]);
    }

}
