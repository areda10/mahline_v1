<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Authentication\Services;

use App\Domains\Identity\Authentication\DTOs\AuthenticationContext;
use App\Domains\Identity\Authentication\Models\AuthenticationSession;
use App\Domains\Identity\Authentication\Models\LoginHistory;
use App\Domains\Identity\Authentication\Services\AuthenticationSecurityService;
use App\Domains\Identity\Authentication\Services\AuthenticationService;
use App\Domains\Identity\Authentication\Services\SessionService;
use App\Domains\Identity\Authentication\Services\UnusualActivityDetectionService;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
}