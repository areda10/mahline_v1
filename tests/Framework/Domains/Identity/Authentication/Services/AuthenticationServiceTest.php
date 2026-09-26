<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Authentication\Services;

use App\Domains\Identity\Authentication\DTOs\AuthenticationContext;
use App\Domains\Identity\Authentication\Services\AuthenticationSecurityService;
use App\Domains\Identity\Authentication\Services\AuthenticationService;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
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

        $this->expectException(ModelNotFoundException::class);

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
        } catch (ModelNotFoundException) {
            // Expected.
        }

        $this->assertDatabaseHas('login_histories', [
            'email' => 'unknown@example.com',
            'event' => 'failed',
        ]);
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

        $this->expectException(RuntimeException::class);
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
        } catch (RuntimeException) {
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

        $this->expectException(RuntimeException::class);
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
        } catch (RuntimeException) {
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

        $this->expectException(ModelNotFoundException::class);

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

        $this->expectException(ModelNotFoundException::class);

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

        $this->expectException(RuntimeException::class);

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
        } catch (RuntimeException) {
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
        } catch (RuntimeException) {
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

        for ($i = 0; $i < 5; $i++) {
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

        $this->expectException(RuntimeException::class);

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

        for ($i = 0; $i < 5; $i++) {
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
        } catch (RuntimeException) {
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

        for ($i = 0; $i < 5; $i++) {
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
        } catch (RuntimeException) {
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

            $securityService->recordFailedAttemptByIp(
                '127.0.0.16'
            );
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
        } catch (RuntimeException) {
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

}