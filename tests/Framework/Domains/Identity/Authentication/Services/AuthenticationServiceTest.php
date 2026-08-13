<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Authentication\Services;

use App\Domains\Identity\Authentication\DTOs\AuthenticationContext;
use App\Domains\Identity\Authentication\Models\AuthenticationSession;
use App\Domains\Identity\Authentication\Models\LoginHistory;
use App\Domains\Identity\Authentication\Services\AuthenticationService;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

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

        $this->expectException(\RuntimeException::class);
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

        $this->expectException(\RuntimeException::class);
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

    /**
     * Test that a second successful login replaces the previous
     * authenticated session.
     *
     * Example:
     *
     * Android / Firefox
     *       ↓
     * Session A
     *
     * Then:
     *
     * iPhone / Safari
     *       ↓
     * Session B
     *
     * Session A must no longer be active.
     *
     * MAHLINE rule:
     *
     * ONE USER → ONE ACTIVE AUTHENTICATED SESSION
     */
    public function test_new_login_replaces_previous_session(): void
    {
        /*
        * ================================================================
        * ARRANGE
        * ================================================================
        *
        * Create one user.
        */
        $user = User::factory()->create([
            'email' => 'session-replacement@example.com',
            'password' => 'password',
        ]);

        /*
        * First authentication context.
        *
        * Simulates:
        *
        * Android + Firefox
        */
        $firstContext = new AuthenticationContext(
            email: 'session-replacement@example.com',
            password: 'password',
            sessionId: 'session-android',
            ipAddress: '127.0.0.1',
            userAgent: 'Mozilla/5.0 Android',
            browser: 'Firefox',
            device: 'Android',
        );

        /*
        * ================================================================
        * ACT
        * ================================================================
        *
        * First login.
        */
        $this->authenticationService->authenticate(
            $firstContext
        );

        /*
        * Retrieve the first authentication session.
        */
        $firstSession = AuthenticationSession::query()
            ->where('user_id', $user->getKey())
            ->where('session_id', 'session-android')
            ->firstOrFail();

        /*
        * The first session must initially be active.
        */
        $this->assertNull(
            $firstSession->revoked_at
        );

        /*
        * Second authentication context.
        *
        * Simulates:
        *
        * iPhone + Safari
        */
        $secondContext = new AuthenticationContext(
            email: 'session-replacement@example.com',
            password: 'password',
            sessionId: 'session-iphone',
            ipAddress: '127.0.0.2',
            userAgent: 'Mozilla/5.0 iPhone',
            browser: 'Safari',
            device: 'iPhone',
        );

        /*
        * Second login.
        *
        * SessionService must revoke the first session and create
        * the new active session.
        */
        $this->authenticationService->authenticate(
            $secondContext
        );

        /*
        * ================================================================
        * ASSERT
        * ================================================================
        *
        * Refresh the first session from the database.
        */
        $firstSession->refresh();

        /*
        * The first session must still exist.
        *
        * We keep it as authentication history.
        */
        $this->assertTrue(
            AuthenticationSession::query()
                ->whereKey($firstSession->getKey())
                ->exists()
        );

        /*
        * The first session must now be revoked.
        */
        $this->assertNotNull(
            $firstSession->revoked_at
        );

        /*
        * The reason must indicate that a new login replaced it.
        */
        $this->assertSame(
            'new_login',
            $firstSession->revocation_reason
        );

        /*
        * The second session must exist.
        */
        $secondSession = AuthenticationSession::query()
            ->where('user_id', $user->getKey())
            ->where('session_id', 'session-iphone')
            ->firstOrFail();

        /*
        * The second session must be active.
        */
        $this->assertNull(
            $secondSession->revoked_at
        );

        /*
        * ================================================================
        * ONE USER → ONE ACTIVE AUTHENTICATED SESSION
        * ================================================================
        *
        * IMPORTANT:
        *
        * There are now TWO session records:
        *
        *     Android → revoked
        *     iPhone  → active
        *
        * Therefore total session count = 2.
        *
        * What must equal 1 is the number of ACTIVE sessions.
        */
        $this->assertSame(
            1,
            AuthenticationSession::query()
                ->where('user_id', $user->getKey())
                ->whereNull('revoked_at')
                ->count()
        );

        /*
        * Verify that two historical records exist.
        */
        $this->assertSame(
            2,
            AuthenticationSession::query()
                ->where('user_id', $user->getKey())
                ->count()
        );

        /*
        * ================================================================
        * LOGIN HISTORY
        * ================================================================
        *
        * The replacement of the first session must also be recorded.
        */
        $this->assertDatabaseHas(
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
}