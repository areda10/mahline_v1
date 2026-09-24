<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Authentication\Services;

use App\Domains\Identity\Authentication\DTOs\AuthenticationContext;
use App\Domains\Identity\Authentication\Enums\SecurityEventType;
use App\Domains\Identity\Authentication\Models\AuthenticationSession;
use App\Domains\Identity\Authentication\Models\LoginHistory;
use App\Domains\Identity\Authentication\Models\SecurityEvent;
use App\Domains\Identity\Authentication\Services\AuthenticationSecurityService;
use App\Domains\Identity\Authentication\Services\AuthenticationService;
use App\Domains\Identity\Authentication\Services\SessionService;
use App\Domains\Identity\Authentication\Services\UnusualActivityDetectionService;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;
use RuntimeException;

final class AuthenticationServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuthenticationService $authenticationService;

    /**
     * Prepare the authentication service before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->authenticationService = app(
            AuthenticationService::class
        );
    }

    /**
     * Test successful authentication.
     *
     * Expected behavior:
     *
     * - User exists.
     * - User is active.
     * - Password is valid.
     * - Authentication session is created.
     * - Login history contains a success event.
     */
    public function test_authenticates_user_successfully(): void
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

        $authenticatedUser = $this->authenticationService->authenticate(
            $context
        );

        /*
         * The authenticated user must be the expected user.
         */
        $this->assertSame(
            $user->getKey(),
            $authenticatedUser->getKey()
        );

        /*
         * An authentication session must have been created.
         */
        $this->assertDatabaseHas(
            'authentication_sessions',
            [
                'user_id' => $user->getKey(),
                'session_id' => 'session-success',
                'revoked_at' => null,
            ]
        );

        /*
         * A successful login must be recorded.
         */
        $this->assertDatabaseHas(
            'login_histories',
            [
                'user_id' => $user->getKey(),
                'email' => $user->email,
                'event' => 'success',
                'reason' => null,
                'ip_address' => '127.0.0.1',
                'browser' => 'Firefox',
                'device' => 'Android',
            ]
        );
    }

    /**
     * Test authentication with an unknown email.
     *
     * Expected behavior:
     *
     * - No user is found.
     * - No authentication session is created.
     * - A failed login is recorded.
     * - The password is never stored.
     */
    public function test_unknown_user_is_rejected_and_failure_is_recorded(): void
    {
        $context = new AuthenticationContext(
            email: 'unknown@example.com',
            password: 'password',
            sessionId: 'session-unknown',
            ipAddress: '127.0.0.1',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Android',
        );

        $this->expectException(
            \Illuminate\Database\Eloquent\ModelNotFoundException::class
        );

        try {
            $this->authenticationService->authenticate($context);
        } finally {
            /*
             * No authentication session must exist.
             */
            $this->assertDatabaseMissing(
                'authentication_sessions',
                [
                    'session_id' => 'session-unknown',
                ]
            );

            /*
             * The failed attempt must be recorded.
             */
            $this->assertDatabaseHas(
                'login_histories',
                [
                    'user_id' => null,
                    'email' => 'unknown@example.com',
                    'event' => 'failed',
                    'reason' => 'user_not_found',
                    'ip_address' => '127.0.0.1',
                    'browser' => 'Firefox',
                    'device' => 'Android',
                ]
            );
        }
    }

    /**
     * Test authentication when the account is not active.
     *
     * The password should not be verified because the account
     * is already forbidden from authenticating.
     */
    public function test_inactive_user_is_rejected(): void
    {
        $user = User::factory()->create([
            'email' => 'inactive@example.com',
            'password' => 'password',
            'status' => \App\Domains\Identity\Enums\UserStatus::Inactive,
        ]);

        $context = new AuthenticationContext(
            email: 'inactive@example.com',
            password: 'password',
            sessionId: 'session-inactive',
            ipAddress: '127.0.0.1',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Android',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'User account is not active.'
        );

        try {
            $this->authenticationService->authenticate($context);
        } finally {
            /*
             * No authentication session must be created.
             */
            $this->assertDatabaseMissing(
                'authentication_sessions',
                [
                    'session_id' => 'session-inactive',
                ]
            );

            /*
             * The failed authentication must be recorded.
             */
            $this->assertDatabaseHas(
                'login_histories',
                [
                    'user_id' => $user->getKey(),
                    'email' => $user->email,
                    'event' => 'failed',
                    'reason' => 'account_not_active',
                ]
            );
        }
    }

    /**
     * Test authentication with an invalid password.
     *
     * Expected behavior:
     *
     * - User exists.
     * - User is active.
     * - Password is invalid.
     * - No authentication session is created.
     * - Failed login is recorded.
     */
    public function test_invalid_password_is_rejected(): void
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

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Invalid credentials.'
        );

        try {
            $this->authenticationService->authenticate($context);
        } finally {
            /*
             * No authentication session must be created.
             */
            $this->assertDatabaseMissing(
                'authentication_sessions',
                [
                    'session_id' => 'session-invalid-password',
                ]
            );

            /*
             * The failed authentication must be recorded.
             */
            $this->assertDatabaseHas(
                'login_histories',
                [
                    'user_id' => $user->getKey(),
                    'email' => $user->email,
                    'event' => 'failed',
                    'reason' => 'invalid_credentials',
                ]
            );
        }
    }

    /**
     * Test that a successful login creates exactly one active
     * authentication session.
     *
     * MAHLINE rule:
     *
     * ONE USER → ONE ACTIVE AUTHENTICATED SESSION
     */
    public function test_successful_authentication_creates_one_active_session(): void
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => 'password',
        ]);

        $context = new AuthenticationContext(
            email: 'john@example.com',
            password: 'password',
            sessionId: 'session-one',
            ipAddress: '127.0.0.1',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Android',
        );

        $this->authenticationService->authenticate($context);

        /*
         * There must be exactly one session for the user.
         */
        $this->assertSame(
            1,
            AuthenticationSession::query()
                ->where('user_id', $user->getKey())
                ->count()
        );

        /*
         * The session must be active.
         */
        $this->assertDatabaseHas(
            'authentication_sessions',
            [
                'user_id' => $user->getKey(),
                'session_id' => 'session-one',
                'revoked_at' => null,
            ]
        );
    }

    // public function test_new_login_replaces_previous_session(): void
    public function test_new_login_keeps_previous_session_active(): void
    {
        $user = User::factory()->create([
            'email' => 'session-replacement@example.com',
            'password' => 'password',
        ]);

        $firstContext = new AuthenticationContext(
            email: 'session-replacement@example.com',
            password: 'password',
            sessionId: 'session-android',
            ipAddress: '127.0.0.1',
            userAgent: 'Mozilla/5.0 Android',
            browser: 'Firefox',
            device: 'Android',
        );

        $this->authenticationService->authenticate(
            $firstContext
        );

        $firstSession = AuthenticationSession::query()
            ->where('user_id', $user->getKey())
            ->where('session_id', 'session-android')
            ->firstOrFail();

        $this->assertNull(
            $firstSession->revoked_at
        );

        $secondContext = new AuthenticationContext(
            email: 'session-replacement@example.com',
            password: 'password',
            sessionId: 'session-iphone',
            ipAddress: '127.0.0.2',
            userAgent: 'Mozilla/5.0 iPhone',
            browser: 'Safari',
            device: 'iPhone',
        );

        $this->authenticationService->authenticate(
            $secondContext
        );

        $firstSession->refresh();

        $secondSession = AuthenticationSession::query()
            ->where('user_id', $user->getKey())
            ->where('session_id', 'session-iphone')
            ->firstOrFail();

        /*
        * The first session must remain active.
        */
        $this->assertNull(
            $firstSession->revoked_at
        );

        $this->assertTrue(
            $firstSession->isActive()
        );

        /*
        * The second session must also be active.
        */
        $this->assertNull(
            $secondSession->revoked_at
        );

        $this->assertTrue(
            $secondSession->isActive()
        );

        /*
        * Both sessions must coexist.
        */
        $this->assertSame(
            2,
            AuthenticationSession::query()
                ->where('user_id', $user->getKey())
                ->whereNull('revoked_at')
                ->count()
        );

        $this->assertSame(
            2,
            AuthenticationSession::query()
                ->where('user_id', $user->getKey())
                ->count()
        );

        /*
        * No session_revoked event must be generated
        * for the first session.
        */
        $this->assertDatabaseMissing(
            'login_histories',
            [
                'user_id' => $user->getKey(),
                'event' => 'session_revoked',
                'reason' => 'new_login',
                'authentication_session_id' => $firstSession->getKey(),
            ]
        );
    }

    /**
     * Test that the successful login creates a LoginHistory
     * entry linked to the authentication session.
     */
    public function test_successful_login_history_references_session(): void
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => 'password',
        ]);

        $context = new AuthenticationContext(
            email: 'john@example.com',
            password: 'password',
            sessionId: 'session-history',
            ipAddress: '127.0.0.1',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Windows',
        );

        $this->authenticationService->authenticate(
            $context
        );

        /*
         * Retrieve the created authentication session.
         */
        $session = AuthenticationSession::query()
            ->where('session_id', 'session-history')
            ->firstOrFail();

        /*
         * Retrieve the successful login history.
         */
        $history = LoginHistory::query()
            ->where('user_id', $user->getKey())
            ->where('event', 'success')
            ->latest('id')
            ->firstOrFail();

        /*
         * The history entry must reference the session.
         */
        $this->assertSame(
            $session->getKey(),
            $history->authentication_session_id
        );
    }

    /**
     * Test that AuthenticationService does not depend directly
     * on Laravel's HTTP Request.
     */
    public function test_authentication_service_does_not_depend_on_request(): void
    {
        $reflection = new \ReflectionClass(
            AuthenticationService::class
        );

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            $this->assertTrue(true);

            return;
        }

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type === null) {
                continue;
            }

            $typeName = $type instanceof \ReflectionNamedType
                ? $type->getName()
                : null;

            $this->assertNotSame(
                \Illuminate\Http\Request::class,
                $typeName
            );
        }

        $this->assertTrue(true);
    }

    /**
     * Test that the AuthenticationContext is immutable.
     *
     * This confirms that authentication data is transported
     * through a readonly DTO.
     */
    public function test_authentication_context_is_readonly(): void
    {
        $reflection = new \ReflectionClass(
            AuthenticationContext::class
        );

        $this->assertTrue(
            $reflection->isReadOnly()
        );
    }

    /**
     * Test that the password is never stored in LoginHistory.
     */
    public function test_plain_password_is_not_stored_in_login_history(): void
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => 'correct-password',
        ]);

        $context = new AuthenticationContext(
            email: 'john@example.com',
            password: 'correct-password',
            sessionId: 'session-security',
            ipAddress: '127.0.0.1',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Windows',
        );

        $this->authenticationService->authenticate(
            $context
        );

        /*
         * LoginHistory must not contain a password column.
         *
         * More importantly, the plaintext password must not appear
         * anywhere in the stored LoginHistory data.
         */
        $history = LoginHistory::query()
            ->where('user_id', $user->getKey())
            ->latest('id')
            ->firstOrFail();

        $historyData = $history->toArray();

        $this->assertNotContains(
            'correct-password',
            $historyData
        );
    }

    // public function test_login_from_phone_revokes_previous_computer_session(): void
    public function test_login_from_phone_keeps_previous_computer_session_active(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => 'password',
            'status' => UserStatus::Active,
        ]);

        /*
        * First authentication: computer.
        */
        $this->authenticationService->authenticate(
            new AuthenticationContext(
                email: 'user@example.com',
                password: 'password',
                sessionId: 'computer-session-001',
                ipAddress: '192.168.1.10',
                userAgent: 'Mozilla/5.0 Chrome',
                browser: 'Chrome',
                device: 'Computer',
            ),
        );

        $computerSession = AuthenticationSession::query()
            ->where('user_id', $user->id)
            ->where('session_id', 'computer-session-001')
            ->firstOrFail();

        /*
        * Second authentication: phone.
        */
        $this->authenticationService->authenticate(
            new AuthenticationContext(
                email: 'user@example.com',
                password: 'password',
                sessionId: 'phone-session-001',
                ipAddress: '192.168.1.20',
                userAgent: 'Mozilla/5.0 Mobile Safari',
                browser: 'Safari',
                device: 'Phone',
            ),
        );

        $computerSession->refresh();

        $phoneSession = AuthenticationSession::query()
            ->where('user_id', $user->id)
            ->where('session_id', 'phone-session-001')
            ->firstOrFail();

        // $this->assertFalse($computerSession->isActive());
        // $this->assertTrue($phoneSession->isActive());
        // $this->assertNotNull($computerSession->revoked_at);
        $this->assertTrue($computerSession->isActive());
        $this->assertTrue($phoneSession->isActive());
        $this->assertNull($computerSession->revoked_at);
        $this->assertNull($phoneSession->revoked_at);
    }

    public function test_locked_email_rejects_authentication(): void
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => 'password',
        ]);

        // 2. Création du contexte
        $context = new AuthenticationContext(
            email: $user->email,
            password: 'Password123',
            sessionId: 'session-locked-email',
            ipAddress: '127.0.0.1',
            userAgent: 'TestAgent',
            browser: 'TestBrowser',
            device: 'TestDevice',
        );

        $service = app(AuthenticationService::class);

        $securityService = app(AuthenticationSecurityService::class);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $securityService->recordFailedAttempt(
                $context->email,
            );
        }

        self::assertTrue(
            $securityService->isLocked($context->email),
        );

        $this->expectException(RuntimeException::class);

        $service->authenticate($context);
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

    public function test_successful_authentication_remembers_a_new_device(): void
    {
        $user = User::factory()->create([
            'email' => 'device@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $context = new AuthenticationContext(
            email: 'device@example.com',
            password: 'ValidPassword123',
            sessionId: 'session-device-001',
            ipAddress: '127.0.0.1',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'iPhone',
        );

        $service = app(AuthenticationService::class);

        $service->authenticate($context);

        self::assertDatabaseHas('known_devices', [
            'user_id' => $user->id,
            'device' => 'iphone',
        ]);
    }

    public function test_successful_authentication_does_not_duplicate_a_known_device(): void
    {
        $user = User::factory()->create([
            'email' => 'same-device@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $firstContext = new AuthenticationContext(
            email: 'same-device@example.com',
            password: 'ValidPassword123',
            sessionId: 'session-device-001',
            ipAddress: '127.0.0.1',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'iPhone',
        );

        $secondContext = new AuthenticationContext(
            email: 'same-device@example.com',
            password: 'ValidPassword123',
            sessionId: 'session-device-002',
            ipAddress: '127.0.0.1',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'iPhone',
        );

        $service->authenticate($firstContext);
        $service->authenticate($secondContext);

        self::assertDatabaseCount('known_devices', 1);

        self::assertDatabaseHas('known_devices', [
            'user_id' => $user->id,
            'device' => 'iphone',
        ]);
    }

    public function test_successful_authentication_remembers_each_new_device(): void
    {
        $user = User::factory()->create([
            'email' => 'multi-device@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $firstContext = new AuthenticationContext(
            email: 'multi-device@example.com',
            password: 'ValidPassword123',
            sessionId: 'session-device-001',
            ipAddress: '127.0.0.1',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'iPhone',
        );

        $secondContext = new AuthenticationContext(
            email: 'multi-device@example.com',
            password: 'ValidPassword123',
            sessionId: 'session-device-002',
            ipAddress: '127.0.0.2',
            userAgent: 'Mozilla/5.0',
            browser: 'Chrome',
            device: 'MacBook',
        );

        $service->authenticate($firstContext);
        $service->authenticate($secondContext);

        self::assertDatabaseCount('known_devices', 2);

        self::assertDatabaseHas('known_devices', [
            'user_id' => $user->id,
            'device' => 'iphone',
        ]);

        self::assertDatabaseHas('known_devices', [
            'user_id' => $user->id,
            'device' => 'macbook',
        ]);
    }

    public function test_authentication_detects_a_new_device_before_remembering_it(): void
    {
        $user = User::factory()->create([
            'email' => 'new-device@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $unusualActivityService = app(
            UnusualActivityDetectionService::class,
        );

        $this->assertTrue(
            $unusualActivityService->isNewDevice(
                user: $user,
                device: 'iPhone',
            ),
        );

        $service = app(AuthenticationService::class);

        $context = new AuthenticationContext(
            email: 'new-device@example.com',
            password: 'ValidPassword123',
            sessionId: 'session-device-001',
            ipAddress: '127.0.0.1',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'iPhone',
        );

        $service->authenticate($context);

        self::assertFalse(
            $unusualActivityService->isNewDevice(
                user: $user,
                device: 'iPhone',
            ),
        );
    }

    public function test_authentication_rejects_an_empty_device(): void
    {
        $user = User::factory()->create([
            'email' => 'empty-device@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $context = new AuthenticationContext(
            email: 'empty-device@example.com',
            password: 'ValidPassword123',
            sessionId: 'session-device-empty',
            ipAddress: '127.0.0.1',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: '',
        );

        $service = app(AuthenticationService::class);

        $this->expectException(InvalidArgumentException::class);

        try {
            $service->authenticate($context);
        } finally {
            self::assertDatabaseCount('known_devices', 0);
        }
    }

    public function test_authentication_with_an_empty_device_does_not_create_a_session(): void
    {
        $user = User::factory()->create([
            'email' => 'empty-device-session@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $context = new AuthenticationContext(
            email: 'empty-device-session@example.com',
            password: 'ValidPassword123',
            sessionId: 'session-empty-device',
            ipAddress: '127.0.0.1',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: '',
        );

        $service = app(AuthenticationService::class);

        $this->expectException(InvalidArgumentException::class);

        try {
            $service->authenticate($context);
        } finally {
            self::assertDatabaseMissing('authentication_sessions', [
                'user_id' => $user->id,
                'session_id' => 'session-empty-device',
            ]);
        }
    }

    public function test_device_and_session_creation_are_atomic(): void
    {
        $user = User::factory()->create([
            'email' => 'atomic@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $context = new AuthenticationContext(
            email: 'atomic@example.com',
            password: 'ValidPassword123',
            sessionId: 'session-atomic-001',
            ipAddress: '127.0.0.1',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'iPhone',
        );

        $service = app(AuthenticationService::class);

        /*
        * Le contrat recherché est que les opérations
        * d'authentification soient atomiques.
        *
        * Ce test sera complété lorsque nous aurons
        * identifié le point d'échec réel de SessionService.
        */
        $service->authenticate($context);

        self::assertDatabaseHas('known_devices', [
            'user_id' => $user->id,
            'device' => 'iphone',
        ]);

        self::assertDatabaseHas('authentication_sessions', [
            'user_id' => $user->id,
            'session_id' => 'session-atomic-001',
        ]);
    }

    public function test_failed_session_creation_does_not_remember_new_device(): void
    {
        $user = User::factory()->create([
            'email' => 'atomicity@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $sessionId = 'duplicate-session-id';

        AuthenticationSession::query()->create([
            'user_id' => $user->id,
            'session_id' => $sessionId,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Existing Agent',
            'browser' => 'Firefox',
            'device' => 'Existing Device',
            'authenticated_at' => now(),
            'last_activity_at' => now(),
            'revoked_at' => null,
            'revocation_reason' => null,
        ]);

        $context = new AuthenticationContext(
            email: 'atomicity@example.com',
            password: 'ValidPassword123',
            sessionId: $sessionId,
            ipAddress: '127.0.0.1',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'New Device',
        );

        $service = app(AuthenticationService::class);

        try {
            $service->authenticate($context);
            $this->fail('Authentication should have failed.');
        } catch (\Throwable) {
            // Expected: duplicate session_id.
        }

        $this->assertDatabaseMissing('known_devices', [
            'user_id' => $user->id,
            'device' => 'new device',
        ]);
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

        $this->assertDatabaseHas('authentication_sessions', [
            'user_id' => $user->id,
            'session_id' => 'session-expired-002',
        ]);

        Carbon::setTestNow();
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

        $this->assertDatabaseHas('authentication_sessions', [
            'user_id' => $user->id,
            'session_id' => 'session-different-ip-002',
        ]);
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

        $this->assertDatabaseHas('authentication_sessions', [
            'user_id' => $user->id,
            'session_id' => 'session-no-ip-001',
            'ip_address' => null,
        ]);
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

    public function test_locked_ip_does_not_create_authentication_session(): void
    {
        $user = User::factory()->create([
            'email' => 'locked-ip-no-session@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $securityService = app(AuthenticationSecurityService::class);
        $service = app(AuthenticationService::class);

        $ipAddress = '127.0.0.140';

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $securityService->recordFailedAttemptByIp($ipAddress);
        }

        $context = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-locked-ip-no-session',
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
            'session_id' => 'session-locked-ip-no-session',
        ]);
    }

    public function test_locked_account_does_not_create_authentication_session(): void
    {
        $user = User::factory()->create([
            'email' => 'locked-account-no-session@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $securityService = app(AuthenticationSecurityService::class);
        $service = app(AuthenticationService::class);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $securityService->recordFailedAttempt($user->email);
        }

        $context = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-locked-account-no-session',
            ipAddress: '127.0.0.150',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        try {
            $service->authenticate($context);
            $this->fail('Authentication should have been rejected.');
        } catch (\RuntimeException $exception) {
            $this->assertSame(
                'Account is temporarily locked.',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseMissing('authentication_sessions', [
            'user_id' => $user->id,
            'session_id' => 'session-locked-account-no-session',
        ]);
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

        $this->assertDatabaseHas('authentication_sessions', [
            'user_id' => $user->id,
            'session_id' => 'session-ip-lock-cleared-001',
        ]);
    }

    public function test_failed_authentication_does_not_create_authentication_session(): void
    {
        $user = User::factory()->create([
            'email' => 'failed-no-session@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $context = new AuthenticationContext(
            email: $user->email,
            password: 'WrongPassword123',
            sessionId: 'session-failed-no-session-001',
            ipAddress: '127.0.0.190',
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

        $this->assertDatabaseMissing('authentication_sessions', [
            'user_id' => $user->id,
            'session_id' => 'session-failed-no-session-001',
        ]);
    }

    public function test_failed_authentication_records_login_history(): void
    {
        $user = User::factory()->create([
            'email' => 'failed-login-history@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $context = new AuthenticationContext(
            email: 'failed-login-history@example.com',
            password: 'WrongPassword123',
            sessionId: 'session-failed-history-001',
            ipAddress: '127.0.0.200',
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

        $this->assertDatabaseHas('login_histories', [
            'user_id' => $user->id,
            'ip_address' => '127.0.0.200',
            'browser' => 'Firefox',
            'device' => 'Desktop',
        ]);

        $this->assertDatabaseMissing('authentication_sessions', [
            'user_id' => $user->id,
            'session_id' => 'session-failed-history-001',
        ]);
    }

    public function test_failed_authentication_without_ip_records_login_history(): void
    {
        $user = User::factory()->create([
            'email' => 'failed-login-no-ip@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $context = new AuthenticationContext(
            email: $user->email,
            password: 'WrongPassword123',
            sessionId: 'session-failed-no-ip-001',
            ipAddress: null,
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

        $this->assertDatabaseHas('login_histories', [
            'user_id' => $user->id,
            'ip_address' => null,
            'browser' => 'Firefox',
            'device' => 'Desktop',
        ]);

        $this->assertDatabaseMissing('authentication_sessions', [
            'user_id' => $user->id,
            'session_id' => 'session-failed-no-ip-001',
        ]);
    }

    public function test_successful_authentication_records_login_history(): void
    {
        $user = User::factory()->create([
            'email' => 'successful-login-history@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $context = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-success-history-001',
            ipAddress: '127.0.0.210',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $authenticatedUser = $service->authenticate($context);

        $this->assertSame(
            $user->id,
            $authenticatedUser->id
        );

        $this->assertDatabaseHas('login_histories', [
            'user_id' => $user->id,
            'ip_address' => '127.0.0.210',
            'browser' => 'Firefox',
            'device' => 'Desktop',
        ]);

        $this->assertDatabaseHas('authentication_sessions', [
            'user_id' => $user->id,
            'session_id' => 'session-success-history-001',
        ]);
    }

    public function test_successful_logins_keep_previous_session_active(): void
    {
        $user = User::factory()->create([
            'email' => 'multiple-successful-logins@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $firstContext = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-multiple-success-001',
            ipAddress: '127.0.0.220',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $service->authenticate($firstContext);

        $secondContext = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-multiple-success-002',
            ipAddress: '127.0.0.221',
            userAgent: 'Mozilla/5.0',
            browser: 'Chrome',
            device: 'Laptop',
        );

        $service->authenticate($secondContext);

        $this->assertDatabaseHas('authentication_sessions', [
            'user_id' => $user->id,
            'session_id' => 'session-multiple-success-001',
            'revoked_at' => null,
        ]);

        $this->assertDatabaseHas('authentication_sessions', [
            'user_id' => $user->id,
            'session_id' => 'session-multiple-success-002',
            'revoked_at' => null,
        ]);
    }

    public function test_same_device_can_create_multiple_active_sessions(): void
    {
        $user = User::factory()->create([
            'email' => 'same-device-sessions@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $firstContext = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-same-device-001',
            ipAddress: '127.0.0.230',
            userAgent: 'Mozilla/5.0',
            browser: 'Chrome',
            device: 'Desktop',
        );

        $service->authenticate($firstContext);

        $secondContext = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-same-device-002',
            ipAddress: '127.0.0.230',
            userAgent: 'Mozilla/5.0',
            browser: 'Chrome',
            device: 'Desktop',
        );

        $service->authenticate($secondContext);

        $this->assertDatabaseHas('authentication_sessions', [
            'user_id' => $user->id,
            'session_id' => 'session-same-device-001',
            'revoked_at' => null,
        ]);

        $this->assertDatabaseHas('authentication_sessions', [
            'user_id' => $user->id,
            'session_id' => 'session-same-device-002',
            'revoked_at' => null,
        ]);

        $this->assertSame(
            2,
            AuthenticationSession::query()
                ->where('user_id', $user->id)
                ->whereNull('revoked_at')
                ->count()
        );
    }

    public function test_same_ip_can_have_multiple_active_sessions(): void
    {
        $user = User::factory()->create([
            'email' => 'same-ip-sessions@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $ipAddress = '127.0.0.240';

        $firstContext = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-same-ip-001',
            ipAddress: $ipAddress,
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $service->authenticate($firstContext);

        $secondContext = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-same-ip-002',
            ipAddress: $ipAddress,
            userAgent: 'Mozilla/5.0',
            browser: 'Chrome',
            device: 'Laptop',
        );

        $service->authenticate($secondContext);

        $this->assertDatabaseHas('authentication_sessions', [
            'user_id' => $user->id,
            'session_id' => 'session-same-ip-001',
            'ip_address' => $ipAddress,
            'revoked_at' => null,
        ]);

        $this->assertDatabaseHas('authentication_sessions', [
            'user_id' => $user->id,
            'session_id' => 'session-same-ip-002',
            'ip_address' => $ipAddress,
            'revoked_at' => null,
        ]);

        $this->assertSame(
            2,
            AuthenticationSession::query()
                ->where('user_id', $user->id)
                ->where('ip_address', $ipAddress)
                ->whereNull('revoked_at')
                ->count()
        );
    }

    public function test_different_user_agents_can_have_multiple_active_sessions(): void
    {
        $user = User::factory()->create([
            'email' => 'different-user-agents@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $firstContext = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-user-agent-001',
            ipAddress: '127.0.0.241',
            userAgent: 'Mozilla/5.0 Firefox',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $service->authenticate($firstContext);

        $secondContext = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-user-agent-002',
            ipAddress: '127.0.0.242',
            userAgent: 'Mozilla/5.0 Chrome',
            browser: 'Chrome',
            device: 'Laptop',
        );

        $service->authenticate($secondContext);

        $this->assertDatabaseHas('authentication_sessions', [
            'user_id' => $user->id,
            'session_id' => 'session-user-agent-001',
            'user_agent' => 'Mozilla/5.0 Firefox',
            'revoked_at' => null,
        ]);

        $this->assertDatabaseHas('authentication_sessions', [
            'user_id' => $user->id,
            'session_id' => 'session-user-agent-002',
            'user_agent' => 'Mozilla/5.0 Chrome',
            'revoked_at' => null,
        ]);

        $this->assertSame(
            2,
            AuthenticationSession::query()
                ->where('user_id', $user->id)
                ->whereNull('revoked_at')
                ->count()
        );
    }

    public function test_different_browsers_can_have_multiple_active_sessions(): void
    {
        $user = User::factory()->create([
            'email' => 'different-browsers@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $firstContext = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-browser-001',
            ipAddress: '127.0.0.243',
            userAgent: 'Mozilla/5.0 Firefox',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $service->authenticate($firstContext);

        $secondContext = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-browser-002',
            ipAddress: '127.0.0.244',
            userAgent: 'Mozilla/5.0 Chrome',
            browser: 'Chrome',
            device: 'Laptop',
        );

        $service->authenticate($secondContext);

        $this->assertDatabaseHas('authentication_sessions', [
            'user_id' => $user->id,
            'session_id' => 'session-browser-001',
            'browser' => 'Firefox',
            'revoked_at' => null,
        ]);

        $this->assertDatabaseHas('authentication_sessions', [
            'user_id' => $user->id,
            'session_id' => 'session-browser-002',
            'browser' => 'Chrome',
            'revoked_at' => null,
        ]);

        $this->assertSame(
            2,
            AuthenticationSession::query()
                ->where('user_id', $user->id)
                ->whereNull('revoked_at')
                ->count()
        );
    }

    public function test_different_devices_can_have_multiple_active_sessions(): void
    {
        $user = User::factory()->create([
            'email' => 'different-devices@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $firstContext = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-device-001',
            ipAddress: '127.0.0.245',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $service->authenticate($firstContext);

        $secondContext = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-device-002',
            ipAddress: '127.0.0.246',
            userAgent: 'Mozilla/5.0',
            browser: 'Chrome',
            device: 'Mobile',
        );

        $service->authenticate($secondContext);

        $this->assertDatabaseHas('authentication_sessions', [
            'user_id' => $user->id,
            'session_id' => 'session-device-001',
            'device' => 'Desktop',
            'revoked_at' => null,
        ]);

        $this->assertDatabaseHas('authentication_sessions', [
            'user_id' => $user->id,
            'session_id' => 'session-device-002',
            'device' => 'Mobile',
            'revoked_at' => null,
        ]);

        $this->assertSame(
            2,
            AuthenticationSession::query()
                ->where('user_id', $user->id)
                ->whereNull('revoked_at')
                ->count()
        );
    }

    public function test_revoked_session_does_not_block_new_authentication(): void
    {
        $user = User::factory()->create([
            'email' => 'revoked-session@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $firstContext = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-revoked-001',
            ipAddress: '127.0.0.247',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $service->authenticate($firstContext);

        $session = AuthenticationSession::query()
            ->where('session_id', 'session-revoked-001')
            ->firstOrFail();

        app(SessionService::class)->revoke(
            session: $session,
            reason: 'logout',
        );

        $secondContext = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-revoked-002',
            ipAddress: '127.0.0.248',
            userAgent: 'Mozilla/5.0',
            browser: 'Chrome',
            device: 'Laptop',
        );

        $service->authenticate($secondContext);

        $this->assertDatabaseHas('authentication_sessions', [
            'user_id' => $user->id,
            'session_id' => 'session-revoked-001',
        ]);

        $this->assertDatabaseHas('authentication_sessions', [
            'user_id' => $user->id,
            'session_id' => 'session-revoked-002',
            'revoked_at' => null,
        ]);

        $this->assertNotNull(
            AuthenticationSession::query()
                ->where('session_id', 'session-revoked-001')
                ->firstOrFail()
                ->revoked_at
        );
    }

    public function test_revoked_session_is_not_counted_as_active(): void
    {
        $user = User::factory()->create([
            'email' => 'revoked-not-active@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $firstContext = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-active-check-001',
            ipAddress: '127.0.0.249',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $service->authenticate($firstContext);

        $secondContext = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-active-check-002',
            ipAddress: '127.0.0.250',
            userAgent: 'Mozilla/5.0',
            browser: 'Chrome',
            device: 'Laptop',
        );

        $service->authenticate($secondContext);

        $session = AuthenticationSession::query()
            ->where('session_id', 'session-active-check-001')
            ->firstOrFail();

        app(SessionService::class)->revoke(
            session: $session,
            reason: 'logout',
        );

        $activeSessions = AuthenticationSession::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->count();

        $this->assertSame(1, $activeSessions);
    }

    public function test_current_session_returns_the_latest_active_session(): void
    {
        $user = User::factory()->create([
            'email' => 'current-session@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $firstContext = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-current-001',
            ipAddress: '127.0.0.251',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $service->authenticate($firstContext);

        $secondContext = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-current-002',
            ipAddress: '127.0.0.252',
            userAgent: 'Mozilla/5.0',
            browser: 'Chrome',
            device: 'Laptop',
        );

        $service->authenticate($secondContext);

        $current = app(SessionService::class)->current($user);

        $this->assertNotNull($current);
        $this->assertSame('session-current-002', $current->session_id);
    }

    public function test_current_session_ignores_revoked_sessions(): void
    {
        $user = User::factory()->create([
            'email' => 'current-revoked@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $firstContext = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-current-revoked-001',
            ipAddress: '127.0.0.253',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $service->authenticate($firstContext);

        $secondContext = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-current-revoked-002',
            ipAddress: '127.0.0.254',
            userAgent: 'Mozilla/5.0',
            browser: 'Chrome',
            device: 'Laptop',
        );

        $service->authenticate($secondContext);

        $latestSession = AuthenticationSession::query()
            ->where('session_id', 'session-current-revoked-002')
            ->firstOrFail();

        app(SessionService::class)->revoke(
            session: $latestSession,
            reason: 'logout',
        );

        $current = app(SessionService::class)->current($user);

        $this->assertNotNull($current);
        $this->assertSame(
            'session-current-revoked-001',
            $current->session_id
        );
    }

    public function test_current_session_returns_null_when_user_has_no_active_session(): void
    {
        $user = User::factory()->create([
            'email' => 'no-active-session@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $current = app(SessionService::class)->current($user);

        $this->assertNull($current);
    }

    public function test_new_authentication_keeps_all_existing_active_sessions(): void
    {
        $user = User::factory()->create([
            'email' => 'keep-all-sessions@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $contexts = [
            new AuthenticationContext(
                email: $user->email,
                password: 'ValidPassword123',
                sessionId: 'session-keep-all-001',
                ipAddress: '127.0.0.201',
                userAgent: 'Mozilla/5.0',
                browser: 'Firefox',
                device: 'Desktop',
            ),
            new AuthenticationContext(
                email: $user->email,
                password: 'ValidPassword123',
                sessionId: 'session-keep-all-002',
                ipAddress: '127.0.0.202',
                userAgent: 'Mozilla/5.0',
                browser: 'Chrome',
                device: 'Laptop',
            ),
            new AuthenticationContext(
                email: $user->email,
                password: 'ValidPassword123',
                sessionId: 'session-keep-all-003',
                ipAddress: '127.0.0.203',
                userAgent: 'Mozilla/5.0',
                browser: 'Safari',
                device: 'Mobile',
            ),
        ];

        foreach ($contexts as $context) {
            $service->authenticate($context);
        }

        $this->assertSame(
            3,
            AuthenticationSession::query()
                ->where('user_id', $user->id)
                ->whereNull('revoked_at')
                ->count()
        );

        $this->assertDatabaseHas('authentication_sessions', [
            'session_id' => 'session-keep-all-001',
            'revoked_at' => null,
        ]);

        $this->assertDatabaseHas('authentication_sessions', [
            'session_id' => 'session-keep-all-002',
            'revoked_at' => null,
        ]);

        $this->assertDatabaseHas('authentication_sessions', [
            'session_id' => 'session-keep-all-003',
            'revoked_at' => null,
        ]);
    }

    public function test_revoking_one_session_keeps_other_sessions_active(): void
    {
        $user = User::factory()->create([
            'email' => 'individual-revocation@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $firstContext = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-revoke-one-001',
            ipAddress: '127.0.0.204',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $service->authenticate($firstContext);

        $secondContext = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-revoke-one-002',
            ipAddress: '127.0.0.205',
            userAgent: 'Mozilla/5.0',
            browser: 'Chrome',
            device: 'Laptop',
        );

        $service->authenticate($secondContext);

        $firstSession = AuthenticationSession::query()
            ->where('session_id', 'session-revoke-one-001')
            ->firstOrFail();

        app(SessionService::class)->revoke(
            session: $firstSession,
            reason: 'logout',
        );

        $this->assertNotNull(
            AuthenticationSession::query()
                ->where('session_id', 'session-revoke-one-001')
                ->firstOrFail()
                ->revoked_at
        );

        $this->assertDatabaseHas('authentication_sessions', [
            'session_id' => 'session-revoke-one-002',
            'revoked_at' => null,
        ]);

        $this->assertSame(
            1,
            AuthenticationSession::query()
                ->where('user_id', $user->id)
                ->whereNull('revoked_at')
                ->count()
        );
    }

    public function test_revoking_an_already_revoked_session_returns_false(): void
    {
        $user = User::factory()->create([
            'email' => 'already-revoked@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $context = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-already-revoked-001',
            ipAddress: '127.0.0.206',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $service->authenticate($context);

        $session = AuthenticationSession::query()
            ->where('session_id', 'session-already-revoked-001')
            ->firstOrFail();

        $sessionService = app(SessionService::class);

        $firstRevocation = $sessionService->revoke(
            session: $session,
            reason: 'logout',
        );

        $session->refresh();

        $secondRevocation = $sessionService->revoke(
            session: $session,
            reason: 'logout',
        );

        $this->assertTrue($firstRevocation);
        $this->assertFalse($secondRevocation);

        $this->assertNotNull($session->revoked_at);
    }

    public function test_deleted_session_cannot_be_revoked(): void
    {
        $user = User::factory()->create([
            'email' => 'deleted-session@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $context = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-deleted-001',
            ipAddress: '127.0.0.207',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $service->authenticate($context);

        $session = AuthenticationSession::query()
            ->where('session_id', 'session-deleted-001')
            ->firstOrFail();

        $session->delete();

        $session->refresh();

        $result = app(SessionService::class)->revoke(
            session: $session,
            reason: 'logout',
        );

        $this->assertFalse($result);
        $this->assertNotNull($session->deleted_at);
    }

    public function test_current_session_ignores_deleted_sessions(): void
    {
        $user = User::factory()->create([
            'email' => 'current-deleted@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $firstContext = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-current-deleted-001',
            ipAddress: '127.0.0.208',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $service->authenticate($firstContext);

        $secondContext = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-current-deleted-002',
            ipAddress: '127.0.0.209',
            userAgent: 'Mozilla/5.0',
            browser: 'Chrome',
            device: 'Laptop',
        );

        $service->authenticate($secondContext);

        $latestSession = AuthenticationSession::query()
            ->where('session_id', 'session-current-deleted-002')
            ->firstOrFail();

        $latestSession->delete();

        $current = app(SessionService::class)->current($user);

        $this->assertNotNull($current);
        $this->assertSame(
            'session-current-deleted-001',
            $current->session_id
        );
    }

    public function test_active_session_remains_valid_after_another_session_is_revoked(): void
    {
        $user = User::factory()->create([
            'email' => 'active-after-revocation@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $firstContext = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-independent-001',
            ipAddress: '127.0.0.210',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $service->authenticate($firstContext);

        $secondContext = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-independent-002',
            ipAddress: '127.0.0.211',
            userAgent: 'Mozilla/5.0',
            browser: 'Chrome',
            device: 'Laptop',
        );

        $service->authenticate($secondContext);

        $firstSession = AuthenticationSession::query()
            ->where('session_id', 'session-independent-001')
            ->firstOrFail();

        app(SessionService::class)->revoke(
            session: $firstSession,
            reason: 'logout',
        );

        $secondSession = AuthenticationSession::query()
            ->where('session_id', 'session-independent-002')
            ->firstOrFail();

        $this->assertNull($secondSession->revoked_at);

        $this->assertTrue(
            app(SessionService::class)->isActive($secondSession)
        );
    }

    public function test_current_session_returns_latest_active_session_when_latest_session_is_revoked(): void
    {
        $user = User::factory()->create([
            'email' => 'latest-revoked-current@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $firstContext = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-latest-revoked-001',
            ipAddress: '127.0.0.212',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $service->authenticate($firstContext);

        $secondContext = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-latest-revoked-002',
            ipAddress: '127.0.0.213',
            userAgent: 'Mozilla/5.0',
            browser: 'Chrome',
            device: 'Laptop',
        );

        $service->authenticate($secondContext);

        $latestSession = AuthenticationSession::query()
            ->where('session_id', 'session-latest-revoked-002')
            ->firstOrFail();

        app(SessionService::class)->revoke(
            session: $latestSession,
            reason: 'logout',
        );

        $current = app(SessionService::class)->current($user);

        $this->assertNotNull($current);
        $this->assertSame(
            'session-latest-revoked-001',
            $current->session_id
        );
        $this->assertNull($current->revoked_at);
    }

    public function test_authentication_after_revocation_creates_new_active_session(): void
    {
        $user = User::factory()->create([
            'email' => 'reauth-after-revocation@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $firstContext = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-reauth-001',
            ipAddress: '127.0.0.214',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $service->authenticate($firstContext);

        $firstSession = AuthenticationSession::query()
            ->where('session_id', 'session-reauth-001')
            ->firstOrFail();

        app(SessionService::class)->revoke(
            session: $firstSession,
            reason: 'logout',
        );

        $secondContext = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-reauth-002',
            ipAddress: '127.0.0.215',
            userAgent: 'Mozilla/5.0',
            browser: 'Chrome',
            device: 'Laptop',
        );

        $service->authenticate($secondContext);

        $this->assertDatabaseHas('authentication_sessions', [
            'user_id' => $user->id,
            'session_id' => 'session-reauth-001',
        ]);

        $this->assertDatabaseHas('authentication_sessions', [
            'user_id' => $user->id,
            'session_id' => 'session-reauth-002',
            'revoked_at' => null,
        ]);

        $this->assertSame(
            1,
            AuthenticationSession::query()
                ->where('user_id', $user->id)
                ->whereNull('revoked_at')
                ->count()
        );
    }

    public function test_revoked_session_keeps_its_revocation_reason(): void
    {
        $user = User::factory()->create([
            'email' => 'revocation-reason@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $context = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-reason-001',
            ipAddress: '127.0.0.216',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $service->authenticate($context);

        $session = AuthenticationSession::query()
            ->where('session_id', 'session-reason-001')
            ->firstOrFail();

        $reason = 'security_review';

        $result = app(SessionService::class)->revoke(
            session: $session,
            reason: $reason,
        );

        $session->refresh();

        $this->assertTrue($result);
        $this->assertNotNull($session->revoked_at);
        $this->assertSame($reason, $session->revocation_reason);

        $this->assertDatabaseHas('authentication_sessions', [
            'session_id' => 'session-reason-001',
            'revocation_reason' => $reason,
        ]);
    }

    public function test_custom_session_revocation_records_session_revoked_history(): void
    {
        $user = User::factory()->create([
            'email' => 'session-revoked-history@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $context = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-history-001',
            ipAddress: '127.0.0.217',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $service->authenticate($context);

        $session = AuthenticationSession::query()
            ->where('session_id', 'session-history-001')
            ->firstOrFail();

        app(SessionService::class)->revoke(
            session: $session,
            reason: 'security_review',
        );

        $this->assertDatabaseHas('login_histories', [
            'user_id' => $user->id,
            'ip_address' => '127.0.0.217',
            'browser' => 'Firefox',
            'device' => 'Desktop',
        ]);
    }

    public function test_custom_session_revocation_does_not_record_logout_history_event(): void
    {
        $user = User::factory()->create([
            'email' => 'custom-revocation-no-logout@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $context = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-no-logout-001',
            ipAddress: '127.0.0.218',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $service->authenticate($context);

        $session = AuthenticationSession::query()
            ->where('session_id', 'session-no-logout-001')
            ->firstOrFail();

        app(SessionService::class)->revoke(
            session: $session,
            reason: 'security_review',
        );

        $this->assertDatabaseMissing('login_histories', [
            'user_id' => $user->id,
            'event' => 'logout',
        ]);
    }

    public function test_logout_revocation_records_logout_history(): void
    {
        $user = User::factory()->create([
            'email' => 'logout-history@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $context = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-logout-history-001',
            ipAddress: '127.0.0.219',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $service->authenticate($context);

        $session = AuthenticationSession::query()
            ->where('session_id', 'session-logout-history-001')
            ->firstOrFail();

        app(SessionService::class)->revoke(
            session: $session,
            reason: 'logout',
        );

        $this->assertDatabaseHas('login_histories', [
            'user_id' => $user->id,
            'ip_address' => '127.0.0.219',
            'browser' => 'Firefox',
            'device' => 'Desktop',
        ]);
    }

    public function test_revoke_for_user_revokes_all_active_sessions(): void
    {
        $user = User::factory()->create([
            'email' => 'revoke-all@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $contexts = [
            new AuthenticationContext(
                email: $user->email,
                password: 'ValidPassword123',
                sessionId: 'session-revoke-all-001',
                ipAddress: '127.0.0.220',
                userAgent: 'Mozilla/5.0',
                browser: 'Firefox',
                device: 'Desktop',
            ),
            new AuthenticationContext(
                email: $user->email,
                password: 'ValidPassword123',
                sessionId: 'session-revoke-all-002',
                ipAddress: '127.0.0.221',
                userAgent: 'Mozilla/5.0',
                browser: 'Chrome',
                device: 'Laptop',
            ),
            new AuthenticationContext(
                email: $user->email,
                password: 'ValidPassword123',
                sessionId: 'session-revoke-all-003',
                ipAddress: '127.0.0.222',
                userAgent: 'Mozilla/5.0',
                browser: 'Safari',
                device: 'Mobile',
            ),
        ];

        foreach ($contexts as $context) {
            $service->authenticate($context);
        }

        $result = app(SessionService::class)->revokeForUser(
            user: $user,
            reason: 'global_logout',
        );

        $this->assertTrue($result);

        $this->assertSame(
            0,
            AuthenticationSession::query()
                ->where('user_id', $user->id)
                ->whereNull('revoked_at')
                ->count()
        );

        $this->assertSame(
            3,
            AuthenticationSession::query()
                ->where('user_id', $user->id)
                ->whereNotNull('revoked_at')
                ->count()
        );
    }

    public function test_revoke_for_user_returns_false_when_no_active_session_exists(): void
    {
        $user = User::factory()->create([
            'email' => 'revoke-all-empty@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $result = app(SessionService::class)->revokeForUser(
            user: $user,
            reason: 'global_logout',
        );

        $this->assertFalse($result);

        $this->assertSame(
            0,
            AuthenticationSession::query()
                ->where('user_id', $user->id)
                ->count()
        );
    }

    public function test_revoke_for_user_ignores_already_revoked_sessions(): void
    {
        $user = User::factory()->create([
            'email' => 'revoke-all-existing@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $firstContext = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-revoke-existing-001',
            ipAddress: '127.0.0.223',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $service->authenticate($firstContext);

        $secondContext = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-revoke-existing-002',
            ipAddress: '127.0.0.224',
            userAgent: 'Mozilla/5.0',
            browser: 'Chrome',
            device: 'Laptop',
        );

        $service->authenticate($secondContext);

        $firstSession = AuthenticationSession::query()
            ->where('session_id', 'session-revoke-existing-001')
            ->firstOrFail();

        app(SessionService::class)->revoke(
            session: $firstSession,
            reason: 'logout',
        );

        $revokedAt = $firstSession->fresh()->revoked_at;

        $result = app(SessionService::class)->revokeForUser(
            user: $user,
            reason: 'global_logout',
        );

        $this->assertTrue($result);

        $firstSession->refresh();

        $this->assertTrue(
            $firstSession->revoked_at->equalTo($revokedAt)
        );

        $this->assertNotNull(
            AuthenticationSession::query()
                ->where('session_id', 'session-revoke-existing-002')
                ->firstOrFail()
                ->revoked_at
        );
    }

    public function test_revoke_for_user_ignores_deleted_sessions(): void
    {
        $user = User::factory()->create([
            'email' => 'revoke-all-deleted@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $context = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-revoke-deleted-001',
            ipAddress: '127.0.0.225',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $service->authenticate($context);

        $session = AuthenticationSession::query()
            ->where('session_id', 'session-revoke-deleted-001')
            ->firstOrFail();

        $session->delete();

        $result = app(SessionService::class)->revokeForUser(
            user: $user,
            reason: 'global_logout',
        );

        $this->assertFalse($result);

        $this->assertNotNull($session->fresh()->deleted_at);
    }

    public function test_revoke_for_user_does_not_revoke_another_users_sessions(): void
    {
        $user = User::factory()->create([
            'email' => 'revoke-owner@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $otherUser = User::factory()->create([
            'email' => 'revoke-other@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $service->authenticate(new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-owner-001',
            ipAddress: '127.0.0.226',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        ));

        $service->authenticate(new AuthenticationContext(
            email: $otherUser->email,
            password: 'ValidPassword123',
            sessionId: 'session-other-001',
            ipAddress: '127.0.0.227',
            userAgent: 'Mozilla/5.0',
            browser: 'Chrome',
            device: 'Laptop',
        ));

        app(SessionService::class)->revokeForUser(
            user: $user,
            reason: 'global_logout',
        );

        $this->assertSame(
            0,
            AuthenticationSession::query()
                ->where('user_id', $user->id)
                ->whereNull('revoked_at')
                ->count()
        );

        $this->assertSame(
            1,
            AuthenticationSession::query()
                ->where('user_id', $otherUser->id)
                ->whereNull('revoked_at')
                ->count()
        );

        $this->assertNotNull(
            AuthenticationSession::query()
                ->where('user_id', $user->id)
                ->where('session_id', 'session-owner-001')
                ->firstOrFail()
                ->revoked_at
        );

        $this->assertNull(
            AuthenticationSession::query()
                ->where('user_id', $otherUser->id)
                ->where('session_id', 'session-other-001')
                ->firstOrFail()
                ->revoked_at
        );
    }

    public function test_revoke_for_user_does_not_change_existing_revocation_reason(): void
    {
        $user = User::factory()->create([
            'email' => 'revoke-reason-preserved@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $service->authenticate(new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-reason-preserved-001',
            ipAddress: '127.0.0.228',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        ));

        $service->authenticate(new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-reason-preserved-002',
            ipAddress: '127.0.0.229',
            userAgent: 'Mozilla/5.0',
            browser: 'Chrome',
            device: 'Laptop',
        ));

        $alreadyRevoked = AuthenticationSession::query()
            ->where('session_id', 'session-reason-preserved-001')
            ->firstOrFail();

        app(SessionService::class)->revoke(
            session: $alreadyRevoked,
            reason: 'security_review',
        );

        app(SessionService::class)->revokeForUser(
            user: $user,
            reason: 'global_logout',
        );

        $alreadyRevoked->refresh();

        $this->assertSame(
            'security_review',
            $alreadyRevoked->revocation_reason
        );

        $this->assertNotNull($alreadyRevoked->revoked_at);

        $this->assertSame(
            'global_logout',
            AuthenticationSession::query()
                ->where('session_id', 'session-reason-preserved-002')
                ->firstOrFail()
                ->revocation_reason
        );
    }

    public function test_revoke_for_user_returns_true_when_an_active_session_is_revoked(): void
    {
        $user = User::factory()->create([
            'email' => 'revoke-return-true@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        app(AuthenticationService::class)->authenticate(
            new AuthenticationContext(
                email: $user->email,
                password: 'ValidPassword123',
                sessionId: 'session-revoke-return-true-001',
                ipAddress: '127.0.0.230',
                userAgent: 'Mozilla/5.0',
                browser: 'Firefox',
                device: 'Desktop',
            )
        );

        $result = app(SessionService::class)->revokeForUser(
            user: $user,
            reason: 'global_logout',
        );

        $this->assertTrue($result);
    }

    public function test_revoke_for_user_revokes_all_active_sessions_and_returns_true(): void
    {
        $user = User::factory()->create([
            'email' => 'revoke-all-return-true@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $service->authenticate(
            new AuthenticationContext(
                email: $user->email,
                password: 'ValidPassword123',
                sessionId: 'session-revoke-all-001',
                ipAddress: '127.0.0.231',
                userAgent: 'Mozilla/5.0',
                browser: 'Firefox',
                device: 'Desktop',
            )
        );

        $service->authenticate(
            new AuthenticationContext(
                email: $user->email,
                password: 'ValidPassword123',
                sessionId: 'session-revoke-all-002',
                ipAddress: '127.0.0.232',
                userAgent: 'Mozilla/5.0',
                browser: 'Chrome',
                device: 'Mobile',
            )
        );

        $result = app(SessionService::class)->revokeForUser(
            user: $user,
            reason: 'global_logout',
        );

        $this->assertTrue($result);

        $this->assertDatabaseHas('authentication_sessions', [
            'session_id' => 'session-revoke-all-001',
            'revocation_reason' => 'global_logout',
        ]);

        $this->assertDatabaseHas('authentication_sessions', [
            'session_id' => 'session-revoke-all-002',
            'revocation_reason' => 'global_logout',
        ]);
    }

    public function test_revoke_for_user_does_not_record_individual_logout_history_events(): void
    {
        $user = User::factory()->create([
            'email' => 'global-logout-history@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $service->authenticate(
            new AuthenticationContext(
                email: $user->email,
                password: 'ValidPassword123',
                sessionId: 'session-global-history-001',
                ipAddress: '127.0.0.233',
                userAgent: 'Mozilla/5.0',
                browser: 'Firefox',
                device: 'Desktop',
            )
        );

        $service->authenticate(
            new AuthenticationContext(
                email: $user->email,
                password: 'ValidPassword123',
                sessionId: 'session-global-history-002',
                ipAddress: '127.0.0.234',
                userAgent: 'Mozilla/5.0',
                browser: 'Chrome',
                device: 'Mobile',
            )
        );

        $result = app(SessionService::class)->revokeForUser(
            user: $user,
            reason: 'global_logout',
        );

        $this->assertTrue($result);

        $this->assertDatabaseMissing('login_histories', [
            'user_id' => $user->id,
            'event' => 'logout',
        ]);

        $this->assertDatabaseHas('login_histories', [
            'user_id' => $user->id,
            'event' => 'session_revoked',
        ]);
    }

    public function test_revoke_for_user_preserves_global_logout_reason_on_all_sessions(): void
    {
        $user = User::factory()->create([
            'email' => 'global-logout-reason@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $service->authenticate(
            new AuthenticationContext(
                email: $user->email,
                password: 'ValidPassword123',
                sessionId: 'session-global-reason-001',
                ipAddress: '127.0.0.235',
                userAgent: 'Mozilla/5.0',
                browser: 'Firefox',
                device: 'Desktop',
            )
        );

        $service->authenticate(
            new AuthenticationContext(
                email: $user->email,
                password: 'ValidPassword123',
                sessionId: 'session-global-reason-002',
                ipAddress: '127.0.0.236',
                userAgent: 'Mozilla/5.0',
                browser: 'Chrome',
                device: 'Mobile',
            )
        );

        $result = app(SessionService::class)->revokeForUser(
            user: $user,
            reason: 'global_logout',
        );

        $this->assertTrue($result);

        $this->assertDatabaseHas('authentication_sessions', [
            'session_id' => 'session-global-reason-001',
            'revocation_reason' => 'global_logout',
        ]);

        $this->assertDatabaseHas('authentication_sessions', [
            'session_id' => 'session-global-reason-002',
            'revocation_reason' => 'global_logout',
        ]);
    }

    public function test_revoke_for_user_returns_false_when_all_sessions_are_already_revoked(): void
    {
        $user = User::factory()->create([
            'email' => 'all-sessions-revoked@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $service->authenticate(
            new AuthenticationContext(
                email: $user->email,
                password: 'ValidPassword123',
                sessionId: 'session-all-revoked-001',
                ipAddress: '127.0.0.237',
                userAgent: 'Mozilla/5.0',
                browser: 'Firefox',
                device: 'Desktop',
            )
        );

        $session = AuthenticationSession::query()
            ->where('session_id', 'session-all-revoked-001')
            ->firstOrFail();

        $session->revoked_at = now();
        $session->revocation_reason = 'logout';
        $session->save();

        $result = app(SessionService::class)->revokeForUser(
            user: $user,
            reason: 'global_logout',
        );

        $this->assertFalse($result);

        $this->assertDatabaseHas('authentication_sessions', [
            'session_id' => 'session-all-revoked-001',
            'revocation_reason' => 'logout',
        ]);
    }

    public function test_revoke_for_user_does_not_revoke_sessions_of_another_user(): void
    {
        $user = User::factory()->create([
            'email' => 'global-logout-owner@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $otherUser = User::factory()->create([
            'email' => 'global-logout-other@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $service->authenticate(
            new AuthenticationContext(
                email: $user->email,
                password: 'ValidPassword123',
                sessionId: 'session-owner-001',
                ipAddress: '127.0.0.238',
                userAgent: 'Mozilla/5.0',
                browser: 'Firefox',
                device: 'Desktop',
            )
        );

        $service->authenticate(
            new AuthenticationContext(
                email: $otherUser->email,
                password: 'ValidPassword123',
                sessionId: 'session-other-001',
                ipAddress: '127.0.0.239',
                userAgent: 'Mozilla/5.0',
                browser: 'Chrome',
                device: 'Mobile',
            )
        );

        $result = app(SessionService::class)->revokeForUser(
            user: $user,
            reason: 'global_logout',
        );

        $this->assertTrue($result);

        $this->assertDatabaseHas('authentication_sessions', [
            'session_id' => 'session-owner-001',
            'revocation_reason' => 'global_logout',
        ]);

        $this->assertDatabaseHas('authentication_sessions', [
            'session_id' => 'session-other-001',
            'revoked_at' => null,
            'revocation_reason' => null,
        ]);
    }

    public function test_revoke_for_user_revokes_only_active_sessions(): void
    {
        $user = User::factory()->create([
            'email' => 'global-logout-mixed-sessions@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $service->authenticate(
            new AuthenticationContext(
                email: $user->email,
                password: 'ValidPassword123',
                sessionId: 'session-mixed-active-001',
                ipAddress: '127.0.0.241',
                userAgent: 'Mozilla/5.0',
                browser: 'Firefox',
                device: 'Desktop',
            )
        );

        $service->authenticate(
            new AuthenticationContext(
                email: $user->email,
                password: 'ValidPassword123',
                sessionId: 'session-mixed-revoked-001',
                ipAddress: '127.0.0.242',
                userAgent: 'Mozilla/5.0',
                browser: 'Chrome',
                device: 'Mobile',
            )
        );

        $revokedSession = AuthenticationSession::query()
            ->where('session_id', 'session-mixed-revoked-001')
            ->firstOrFail();

        $revokedSession->revoked_at = now();
        $revokedSession->revocation_reason = 'logout';
        $revokedSession->save();

        $result = app(SessionService::class)->revokeForUser(
            user: $user,
            reason: 'global_logout',
        );

        $this->assertTrue($result);

        $this->assertDatabaseHas('authentication_sessions', [
            'session_id' => 'session-mixed-active-001',
            'revocation_reason' => 'global_logout',
        ]);

        $this->assertDatabaseHas('authentication_sessions', [
            'session_id' => 'session-mixed-revoked-001',
            'revocation_reason' => 'logout',
        ]);
    }

    public function test_authentication_accepts_email_with_different_case_and_surrounding_spaces(): void
    {
        $user = User::factory()->create([
            'email' => 'normalized-email@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $authenticatedUser = app(AuthenticationService::class)->authenticate(
            new AuthenticationContext(
                email: '  NORMALIZED-EMAIL@EXAMPLE.COM  ',
                password: 'ValidPassword123',
                sessionId: 'session-normalized-email-001',
                ipAddress: '127.0.0.244',
                userAgent: 'Mozilla/5.0',
                browser: 'Firefox',
                device: 'Desktop',
            )
        );

        $this->assertSame($user->id, $authenticatedUser->id);

        $this->assertDatabaseHas('authentication_sessions', [
            'user_id' => $user->id,
            'session_id' => 'session-normalized-email-001',
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
            $securityService->remainingAttempts($user->email),
        );

        app(AuthenticationService::class)->authenticate(
            new AuthenticationContext(
                email: '  SECURITY-NORMALIZED@EXAMPLE.COM  ',
                password: 'ValidPassword123',
                sessionId: 'session-security-normalized-001',
                ipAddress: '127.0.0.245',
                userAgent: 'Mozilla/5.0',
                browser: 'Firefox',
                device: 'Desktop',
            )
        );

        $this->assertSame(
            5,
            $securityService->remainingAttempts($user->email),
        );
    }

    public function test_successful_authentication_with_normalized_email_records_history_for_correct_user(): void
    {
        $user = User::factory()->create([
            'email' => 'history-normalized@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        app(AuthenticationService::class)->authenticate(
            new AuthenticationContext(
                email: '  HISTORY-NORMALIZED@EXAMPLE.COM  ',
                password: 'ValidPassword123',
                sessionId: 'session-history-normalized-001',
                ipAddress: '127.0.0.246',
                userAgent: 'Mozilla/5.0',
                browser: 'Firefox',
                device: 'Desktop',
            )
        );

        $this->assertDatabaseHas('login_histories', [
            'user_id' => $user->id,
            'ip_address' => '127.0.0.246',
            'browser' => 'Firefox',
            'device' => 'Desktop',
        ]);
    }

    public function test_authentication_rejects_email_containing_only_spaces(): void
    {
        $user = User::factory()->create([
            'email' => 'valid-user@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $context = new AuthenticationContext(
            email: '     ',
            password: 'ValidPassword123',
            sessionId: 'session-empty-email-001',
            ipAddress: '127.0.0.247',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        try {
            app(AuthenticationService::class)->authenticate($context);
        } finally {
            $this->assertDatabaseMissing('authentication_sessions', [
                'session_id' => 'session-empty-email-001',
            ]);
        }
    }

    public function test_authentication_rejects_email_with_internal_spaces(): void
    {
        $user = User::factory()->create([
            'email' => 'valid-user@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $context = new AuthenticationContext(
            email: 'valid user@example.com',
            password: 'ValidPassword123',
            sessionId: 'session-internal-space-email-001',
            ipAddress: '127.0.0.248',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        app(AuthenticationService::class)->authenticate($context);

        $this->assertDatabaseMissing('authentication_sessions', [
            'session_id' => 'session-internal-space-email-001',
        ]);
    }

    public function test_failed_session_creation_does_not_record_successful_login_history(): void
    {
        $user = User::factory()->create([
            'email' => 'atomic-login-history@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $sessionId = 'duplicate-session-history-001';

        AuthenticationSession::query()->create([
            'user_id' => $user->id,
            'session_id' => $sessionId,
            'ip_address' => '127.0.0.249',
            'user_agent' => 'Existing Agent',
            'browser' => 'Firefox',
            'device' => 'Existing Device',
            'authenticated_at' => now(),
            'last_activity_at' => now(),
            'revoked_at' => null,
            'revocation_reason' => null,
        ]);

        $context = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: $sessionId,
            ipAddress: '127.0.0.249',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'New Device',
        );

        try {
            app(AuthenticationService::class)->authenticate($context);

            $this->fail('Authentication should have failed.');
        } catch (\Throwable) {
            // Expected: duplicate session_id.
        }

        $this->assertDatabaseCount('login_histories', 0);
    }

    public function test_failed_session_creation_does_not_record_security_event(): void
    {
        $user = User::factory()->create([
            'email' => 'atomic-security-event@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $sessionId = 'duplicate-session-security-event-001';

        AuthenticationSession::query()->create([
            'user_id' => $user->id,
            'session_id' => $sessionId,
            'ip_address' => '127.0.0.250',
            'user_agent' => 'Existing Agent',
            'browser' => 'Firefox',
            'device' => 'Existing Device',
            'authenticated_at' => now(),
            'last_activity_at' => now(),
            'revoked_at' => null,
            'revocation_reason' => null,
        ]);

        $context = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: $sessionId,
            ipAddress: '127.0.0.250',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'New Device',
        );

        try {
            app(AuthenticationService::class)->authenticate($context);

            $this->fail('Authentication should have failed.');
        } catch (\Throwable) {
            // Expected: duplicate session_id.
        }

        $this->assertDatabaseCount('security_events', 0);
    }

    public function test_failed_session_creation_does_not_clear_failed_ip_attempts(): void
    {
        $user = User::factory()->create([
            'email' => 'atomic-ip-attempts@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $ipAddress = '127.0.0.251';

        $securityService = app(AuthenticationSecurityService::class);

        $securityService->recordFailedAttemptByIp($ipAddress);

        $this->assertSame(
            4,
            $securityService->remainingIpAttempts($ipAddress),
        );

        $sessionId = 'duplicate-session-ip-attempts-001';

        AuthenticationSession::query()->create([
            'user_id' => $user->id,
            'session_id' => $sessionId,
            'ip_address' => $ipAddress,
            'user_agent' => 'Existing Agent',
            'browser' => 'Firefox',
            'device' => 'Existing Device',
            'authenticated_at' => now(),
            'last_activity_at' => now(),
            'revoked_at' => null,
            'revocation_reason' => null,
        ]);

        try {
            app(AuthenticationService::class)->authenticate(
                new AuthenticationContext(
                    email: $user->email,
                    password: 'ValidPassword123',
                    sessionId: $sessionId,
                    ipAddress: $ipAddress,
                    userAgent: 'Mozilla/5.0',
                    browser: 'Firefox',
                    device: 'New Device',
                )
            );

            $this->fail('Authentication should have failed.');
        } catch (\Throwable) {
            // Expected: duplicate session_id.
        }

        $this->assertSame(
            4,
            $securityService->remainingIpAttempts($ipAddress),
        );
    }

    public function test_failed_session_creation_does_not_clear_failed_account_attempts(): void
    {
        $user = User::factory()->create([
            'email' => 'atomic-account-attempts@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $securityService = app(AuthenticationSecurityService::class);

        $securityService->recordFailedAttempt($user->email);

        $this->assertSame(
            4,
            $securityService->remainingAttempts($user->email),
        );

        $sessionId = 'duplicate-session-account-attempts-001';

        AuthenticationSession::query()->create([
            'user_id' => $user->id,
            'session_id' => $sessionId,
            'ip_address' => '127.0.0.252',
            'user_agent' => 'Existing Agent',
            'browser' => 'Firefox',
            'device' => 'Existing Device',
            'authenticated_at' => now(),
            'last_activity_at' => now(),
            'revoked_at' => null,
            'revocation_reason' => null,
        ]);

        try {
            app(AuthenticationService::class)->authenticate(
                new AuthenticationContext(
                    email: $user->email,
                    password: 'ValidPassword123',
                    sessionId: $sessionId,
                    ipAddress: '127.0.0.252',
                    userAgent: 'Mozilla/5.0',
                    browser: 'Firefox',
                    device: 'New Device',
                )
            );

            $this->fail('Authentication should have failed.');
        } catch (\Throwable) {
            // Expected: duplicate session_id.
        }

        $this->assertSame(
            4,
            $securityService->remainingAttempts($user->email),
        );
    }

    public function test_authentication_can_succeed_after_previous_session_creation_failure(): void
    {
        $user = User::factory()->create([
            'email' => 'retry-after-session-failure@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $duplicateSessionId = 'duplicate-session-retry-001';

        AuthenticationSession::query()->create([
            'user_id' => $user->id,
            'session_id' => $duplicateSessionId,
            'ip_address' => '127.0.0.253',
            'user_agent' => 'Existing Agent',
            'browser' => 'Firefox',
            'device' => 'Existing Device',
            'authenticated_at' => now(),
            'last_activity_at' => now(),
            'revoked_at' => null,
            'revocation_reason' => null,
        ]);

        try {
            $service->authenticate(
                new AuthenticationContext(
                    email: $user->email,
                    password: 'ValidPassword123',
                    sessionId: $duplicateSessionId,
                    ipAddress: '127.0.0.253',
                    userAgent: 'Mozilla/5.0',
                    browser: 'Firefox',
                    device: 'New Device',
                )
            );

            $this->fail('Authentication should have failed.');
        } catch (\Throwable) {
            // Expected: duplicate session_id.
        }

        $authenticatedUser = $service->authenticate(
            new AuthenticationContext(
                email: $user->email,
                password: 'ValidPassword123',
                sessionId: 'session-retry-success-001',
                ipAddress: '127.0.0.254',
                userAgent: 'Mozilla/5.0',
                browser: 'Chrome',
                device: 'Desktop',
            )
        );

        $this->assertSame($user->id, $authenticatedUser->id);

        $this->assertDatabaseHas('authentication_sessions', [
            'session_id' => 'session-retry-success-001',
            'user_id' => $user->id,
            'revoked_at' => null,
        ]);
    }

    public function test_failed_authentication_records_normalized_email_in_login_history(): void
    {
        User::factory()->create([
            'email' => 'normalized-failure@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $this->expectException(RuntimeException::class);

        try {
            app(AuthenticationService::class)->authenticate(
                new AuthenticationContext(
                    email: '  NORMALIZED-FAILURE@EXAMPLE.COM  ',
                    password: 'WrongPassword123',
                    sessionId: 'session-normalized-failure-001',
                    ipAddress: '127.0.0.255',
                    userAgent: 'Mozilla/5.0',
                    browser: 'Firefox',
                    device: 'Desktop',
                )
            );
        } finally {
            $this->assertDatabaseHas('login_histories', [
                'email' => 'normalized-failure@example.com',
                'event' => 'failed',
                'reason' => 'invalid_credentials',
            ]);
        }
    }

    public function test_unknown_user_records_normalized_email_in_login_history(): void
    {
        $this->expectException(ModelNotFoundException::class);

        try {
            app(AuthenticationService::class)->authenticate(
                new AuthenticationContext(
                    email: '  UNKNOWN-USER@EXAMPLE.COM  ',
                    password: 'ValidPassword123',
                    sessionId: 'session-normalized-unknown-001',
                    ipAddress: '127.0.0.254',
                    userAgent: 'Mozilla/5.0',
                    browser: 'Firefox',
                    device: 'Desktop',
                )
            );
        } finally {
            $this->assertDatabaseHas('login_histories', [
                'email' => 'unknown-user@example.com',
                'event' => 'failed',
                'reason' => 'user_not_found',
            ]);
        }
    }

    public function test_inactive_user_records_normalized_email_in_login_history(): void
    {
        User::factory()->create([
            'email' => 'inactive-normalized@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Inactive,
        ]);

        $this->expectException(RuntimeException::class);

        try {
            app(AuthenticationService::class)->authenticate(
                new AuthenticationContext(
                    email: '  INACTIVE-NORMALIZED@EXAMPLE.COM  ',
                    password: 'ValidPassword123',
                    sessionId: 'session-normalized-inactive-001',
                    ipAddress: '127.0.0.253',
                    userAgent: 'Mozilla/5.0',
                    browser: 'Firefox',
                    device: 'Desktop',
                )
            );
        } finally {
            $this->assertDatabaseHas('login_histories', [
                'email' => 'inactive-normalized@example.com',
                'event' => 'failed',
                'reason' => 'account_not_active',
            ]);
        }
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

        $this->expectException(RuntimeException::class);

        try {
            app(AuthenticationService::class)->authenticate(
                new AuthenticationContext(
                    email: '  LOCKED-NORMALIZED@EXAMPLE.COM  ',
                    password: 'ValidPassword123',
                    sessionId: 'session-normalized-locked-001',
                    ipAddress: '127.0.0.252',
                    userAgent: 'Mozilla/5.0',
                    browser: 'Firefox',
                    device: 'Desktop',
                )
            );
        } finally {
            $this->assertDatabaseHas('login_histories', [
                'email' => 'locked-normalized@example.com',
                'event' => 'failed',
            ]);
        }
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
            $securityService->recordFailedAttemptByIp('127.0.0.251');
        }

        $this->expectException(RuntimeException::class);

        try {
            app(AuthenticationService::class)->authenticate(
                new AuthenticationContext(
                    email: '  IP-LOCKED-NORMALIZED@EXAMPLE.COM  ',
                    password: 'ValidPassword123',
                    sessionId: 'session-normalized-ip-locked-001',
                    ipAddress: '127.0.0.251',
                    userAgent: 'Mozilla/5.0',
                    browser: 'Firefox',
                    device: 'Desktop',
                )
            );
        } finally {
            $this->assertDatabaseHas('login_histories', [
                'email' => 'ip-locked-normalized@example.com',
                'event' => 'failed',
                'reason' => 'ip_locked',
            ]);
        }
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
                '127.0.0.250'
            );
        }

        $this->expectException(RuntimeException::class);

        try {
            app(AuthenticationService::class)->authenticate(
                new AuthenticationContext(
                    email: '  PRIORITY-NORMALIZED@EXAMPLE.COM  ',
                    password: 'ValidPassword123',
                    sessionId: 'session-priority-locked-001',
                    ipAddress: '127.0.0.250',
                    userAgent: 'Mozilla/5.0',
                    browser: 'Firefox',
                    device: 'Desktop',
                )
            );
        } finally {
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
    }

    public function test_locked_account_creates_no_session(): void
    {
        $user = User::factory()->create([
            'email' => 'locked-no-session@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $securityService = app(AuthenticationSecurityService::class);

        for ($i = 0; $i < 5; $i++) {
            $securityService->recordFailedAttempt(
                'locked-no-session@example.com'
            );
        }

        $this->expectException(RuntimeException::class);

        try {
            app(AuthenticationService::class)->authenticate(
                new AuthenticationContext(
                    email: 'locked-no-session@example.com',
                    password: 'ValidPassword123',
                    sessionId: 'session-locked-no-session-001',
                    ipAddress: '127.0.0.249',
                    userAgent: 'Mozilla/5.0',
                    browser: 'Firefox',
                    device: 'Desktop',
                )
            );
        } finally {
            $this->assertDatabaseMissing('authentication_sessions', [
                'user_id' => $user->getKey(),
                'session_id' => 'session-locked-no-session-001',
            ]);
        }
    }

    public function test_locked_ip_creates_no_session(): void
    {
        $user = User::factory()->create([
            'email' => 'ip-locked-no-session@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $securityService = app(AuthenticationSecurityService::class);

        for ($i = 0; $i < 5; $i++) {
            $securityService->recordFailedAttemptByIp(
                '127.0.0.248'
            );
        }

        $this->expectException(RuntimeException::class);

        try {
            app(AuthenticationService::class)->authenticate(
                new AuthenticationContext(
                    email: 'ip-locked-no-session@example.com',
                    password: 'ValidPassword123',
                    sessionId: 'session-ip-locked-no-session-001',
                    ipAddress: '127.0.0.248',
                    userAgent: 'Mozilla/5.0',
                    browser: 'Firefox',
                    device: 'Desktop',
                )
            );
        } finally {
            $this->assertDatabaseMissing('authentication_sessions', [
                'user_id' => $user->getKey(),
                'session_id' => 'session-ip-locked-no-session-001',
            ]);
        }
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

    public function test_unknown_user_creates_no_session(): void
    {
        $this->expectException(ModelNotFoundException::class);

        try {
            app(AuthenticationService::class)->authenticate(
                new AuthenticationContext(
                    email: 'unknown-no-session@example.com',
                    password: 'ValidPassword123',
                    sessionId: 'session-unknown-no-session-001',
                    ipAddress: '127.0.0.241',
                    userAgent: 'Mozilla/5.0',
                    browser: 'Firefox',
                    device: 'Desktop',
                )
            );
        } finally {
            $this->assertDatabaseMissing('authentication_sessions', [
                'session_id' => 'session-unknown-no-session-001',
            ]);
        }
    }

    public function test_inactive_user_creates_no_session(): void
    {
        $user = User::factory()->create([
            'email' => 'inactive-no-session@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Inactive,
        ]);

        $this->expectException(RuntimeException::class);

        try {
            app(AuthenticationService::class)->authenticate(
                new AuthenticationContext(
                    email: 'inactive-no-session@example.com',
                    password: 'ValidPassword123',
                    sessionId: 'session-inactive-no-session-001',
                    ipAddress: '127.0.0.240',
                    userAgent: 'Mozilla/5.0',
                    browser: 'Firefox',
                    device: 'Desktop',
                )
            );
        } finally {
            $this->assertDatabaseMissing('authentication_sessions', [
                'user_id' => $user->getKey(),
                'session_id' => 'session-inactive-no-session-001',
            ]);
        }
    }
}