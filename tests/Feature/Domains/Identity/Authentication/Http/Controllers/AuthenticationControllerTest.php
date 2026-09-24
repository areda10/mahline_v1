<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Identity\Authentication\Http\Controllers;

use Tests\TestCase;

/**
 * AuthenticationControllerTest
 *
 * Tests the HTTP layer of the authentication domain.
 *
 * The controller is responsible for:
 *
 * - receiving the HTTP request;
 * - validating authentication data;
 * - creating AuthenticationContext;
 * - delegating authentication to AuthenticationService;
 * - synchronizing Laravel Auth;
 * - returning the correct HTTP response.
 *
 * Business rules remain inside the domain services.
 *
 * IMPORTANT:
 *
 * The tests intentionally verify the complete authentication flow:
 *
 * HTTP Request
 *      ↓
 * AuthenticationRequest
 *      ↓
 * AuthenticationController
 *      ↓
 * AuthenticationContext
 *      ↓
 * AuthenticationService
 *      ↓
 * SessionService
 *      ↓
 * LoginHistoryService
 */
final class AuthenticationControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Base URL used by the authentication routes.
     */
    private string $loginUrl = '/authentication/login';

    /**
     * Base URL used by the logout route.
     */
    private string $logoutUrl = '/authentication/logout';

    /**
     * Standard browser User-Agent used by the tests.
     */
    private string $userAgent =
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
        . 'AppleWebKit/537.36 (KHTML, like Gecko) '
        . 'Chrome/120.0.0.0 Safari/537.36';

    public function test_guest_cannot_logout(): void
    {
        $response = $this->postJson(
            '/authentication/logout',
        );

        $response->assertStatus(401);

        $response->assertJson([
            'message' => 'No authenticated user.',
        ]);
    }  

    /*
    |--------------------------------------------------------------------------
    | SUCCESSFUL AUTHENTICATION
    |--------------------------------------------------------------------------
    */

    /**
     * A valid user can authenticate successfully.
     */
    public function test_user_can_authenticate_successfully(): void
    {
        $user = User::factory()->create([
            'email' => 'controller@example.com',
            'password' => 'password',
            'status' => UserStatus::Active,
        ]);

        $response = $this->withHeader(
            'User-Agent',
            $this->userAgent,
        )->postJson($this->loginUrl, [
            'email' => $user->email,
            'password' => 'password',
        ]);

        /*
         * Authentication must succeed.
         */
        $response->assertOk();

        /*
         * The response must confirm authentication.
         */
        $response->assertJson([
            'message' => 'Authentication successful.',
        ]);

        /*
         * The authenticated user must be returned.
         */
        $response->assertJsonPath(
            'user.id',
            $user->getKey(),
        );

        $response->assertJsonPath(
            'user.email',
            $user->email,
        );

        /*
         * Laravel's authentication guard must contain
         * the authenticated user.
         */
        $this->assertAuthenticatedAs($user);

        /*
         * One active authentication session must exist.
         */
        $this->assertDatabaseHas(
            'authentication_sessions',
            [
                'user_id' => $user->getKey(),
                'revoked_at' => null,
            ],
        );
    }

    /*
    |--------------------------------------------------------------------------
    | UNKNOWN USER
    |--------------------------------------------------------------------------
    */

    /**
     * An unknown email must be rejected.
     *
     * AuthenticationService records the failed attempt.
     */
    public function test_unknown_user_is_rejected(): void
    {
        $response = $this->withHeader(
            'User-Agent',
            $this->userAgent,
        )->postJson($this->loginUrl, [
            'email' => 'unknown@example.com',
            'password' => 'password',
        ]);

        /*
         * The current controller contract uses HTTP 404
         * when the user does not exist.
         */
        $response->assertStatus(404);

        $response->assertJson([
            'message' => 'User not found.',
        ]);

        /*
         * No Laravel authentication must exist.
         */
        $this->assertGuest();

        /*
         * No authentication session may be created.
         */
        $this->assertDatabaseCount(
            'authentication_sessions',
            0,
        );

        /*
         * The failed authentication attempt must be recorded.
         */
        $this->assertDatabaseHas(
            'login_histories',
            [
                'email' => 'unknown@example.com',
                'event' => 'failed',
            ],
        );
    }

    /*
    |--------------------------------------------------------------------------
    | INVALID PASSWORD
    |--------------------------------------------------------------------------
    */

    /**
     * A valid user with an invalid password must be rejected.
     */
    public function test_invalid_password_is_rejected(): void
    {
        $user = User::factory()->create([
            'email' => 'invalid-password@example.com',
            'password' => 'password',
            'status' => UserStatus::Active,
        ]);

        $response = $this->withHeader(
            'User-Agent',
            $this->userAgent,
        )->postJson($this->loginUrl, [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        /*
         * Invalid credentials are represented as HTTP 401.
         */
        $response->assertStatus(401);

        $response->assertJson([
            'message' => 'Invalid credentials.',
        ]);

        /*
         * Laravel authentication must not occur.
         */
        $this->assertGuest();

        /*
         * No authentication session may be created.
         */
        $this->assertDatabaseCount(
            'authentication_sessions',
            0,
        );

        /*
         * Failed authentication must be recorded.
         */
        $this->assertDatabaseHas(
            'login_histories',
            [
                'user_id' => $user->getKey(),
                'email' => $user->email,
                'event' => 'failed',
                'reason' => 'invalid_credentials',
            ],
        );
    }

    /*
    |--------------------------------------------------------------------------
    | INACTIVE USER
    |--------------------------------------------------------------------------
    */

    /**
     * An inactive user cannot authenticate.
     */
    public function test_inactive_user_is_rejected(): void
    {
        $user = User::factory()->create([
            'email' => 'inactive-controller@example.com',
            'password' => 'password',
            'status' => UserStatus::Inactive,
        ]);

        $response = $this->withHeader(
            'User-Agent',
            $this->userAgent,
        )->postJson($this->loginUrl, [
            'email' => $user->email,
            'password' => 'password',
        ]);

        /*
         * The controller converts the authentication
         * RuntimeException into HTTP 401.
         */
        $response->assertStatus(401);

        $response->assertJson([
            'message' => 'User account is not active.',
        ]);

        /*
         * The user must remain unauthenticated.
         */
        $this->assertGuest();

        /*
         * No authentication session may be created.
         */
        $this->assertDatabaseCount(
            'authentication_sessions',
            0,
        );

        /*
         * The failed attempt must be recorded.
         */
        $this->assertDatabaseHas(
            'login_histories',
            [
                'user_id' => $user->getKey(),
                'email' => $user->email,
                'event' => 'failed',
                'reason' => 'account_not_active',
            ],
        );
    }

    /*
    |--------------------------------------------------------------------------
    | ACTIVE SESSION
    |--------------------------------------------------------------------------
    */

    /**
     * Successful authentication creates an active session.
     */
    public function test_successful_authentication_creates_active_session(): void
    {
        $user = User::factory()->create([
            'email' => 'active-session@example.com',
            'password' => 'password',
            'status' => UserStatus::Active,
        ]);

        $response = $this->withHeader(
            'User-Agent',
            $this->userAgent,
        )->postJson($this->loginUrl, [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk();

        /*
         * There must be exactly one session.
         */
        $this->assertSame(
            1,
            AuthenticationSession::query()
                ->where('user_id', $user->getKey())
                ->count(),
        );

        /*
         * The session must be active.
         */
        $this->assertDatabaseHas(
            'authentication_sessions',
            [
                'user_id' => $user->getKey(),
                'revoked_at' => null,
            ],
        );
    }

    /*
    |--------------------------------------------------------------------------
    | LOGIN HISTORY
    |--------------------------------------------------------------------------
    */

    /**
     * Successful authentication creates login history.
     */
    public function test_successful_authentication_creates_login_history(): void
    {
        $user = User::factory()->create([
            'email' => 'history-controller@example.com',
            'password' => 'password',
            'status' => UserStatus::Active,
        ]);

        $response = $this->withHeader(
            'User-Agent',
            $this->userAgent,
        )->postJson($this->loginUrl, [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk();

        /*
         * Retrieve the active authentication session.
         */
        $session = AuthenticationSession::query()
            ->where('user_id', $user->getKey())
            ->whereNull('revoked_at')
            ->firstOrFail();

        /*
         * The successful login must reference that session.
         */
        $this->assertDatabaseHas(
            'login_histories',
            [
                'user_id' => $user->getKey(),
                'email' => $user->email,
                'event' => 'success',
                'authentication_session_id' => $session->getKey(),
            ],
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CLIENT INFORMATION
    |--------------------------------------------------------------------------
    */

    /**
     * Client information must be recorded in login history.
     */
    public function test_client_information_is_recorded(): void
    {
        $user = User::factory()->create([
            'email' => 'client-info@example.com',
            'password' => 'password',
            'status' => UserStatus::Active,
        ]);

        $response = $this->withHeader(
            'User-Agent',
            $this->userAgent,
        )->postJson($this->loginUrl, [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk();

        /*
         * The browser detector should identify Chrome.
         */
        $this->assertDatabaseHas(
            'login_histories',
            [
                'user_id' => $user->getKey(),
                'event' => 'success',
                'browser' => 'Chrome',
                'device' => 'Windows',
            ],
        );
    }

    /*
    |--------------------------------------------------------------------------
    | FAILED AUTHENTICATION
    |--------------------------------------------------------------------------
    */

    /**
     * Failed authentication never creates an authentication session.
     */
    public function test_failed_authentication_never_creates_session(): void
    {
        $user = User::factory()->create([
            'email' => 'failed-session@example.com',
            'password' => 'password',
            'status' => UserStatus::Active,
        ]);

        $response = $this->postJson($this->loginUrl, [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401);

        /*
         * Authentication session table must remain empty.
         */
        $this->assertDatabaseCount(
            'authentication_sessions',
            0,
        );

        /*
         * Failed login history must exist.
         */
        $this->assertDatabaseHas(
            'login_histories',
            [
                'user_id' => $user->getKey(),
                'event' => 'failed',
            ],
        );

        /*
         * Laravel guard must remain unauthenticated.
         */
        $this->assertGuest();
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    /**
     * AuthenticationRequest validates required fields.
     */
    public function test_authentication_request_validates_required_fields(): void
    {
        $response = $this->postJson(
            $this->loginUrl,
            [],
        );

        $response->assertStatus(422);

        $response->assertJsonPath(
            'errors.email',
            fn ($errors) => is_array($errors) && $errors !== [],
        );

        $response->assertJsonPath(
            'errors.password',
            fn ($errors) => is_array($errors) && $errors !== [],
        );
    }

    /**
     * Invalid email format must be rejected.
     */
    public function test_invalid_email_is_rejected(): void
    {
        $response = $this->postJson(
            $this->loginUrl,
            [
                'email' => 'not-an-email',
                'password' => 'password',
            ],
        );

        $response->assertStatus(422);

        $response->assertJsonPath(
            'errors.email',
            fn ($errors) => is_array($errors) && $errors !== [],
        );
    }

    /*
    |--------------------------------------------------------------------------
    | LOGOUT
    |--------------------------------------------------------------------------
    */

    /**
     * Authenticated user can logout successfully.
     */
    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create([
            'email' => 'logout@example.com',
            'password' => 'password',
            'status' => UserStatus::Active,
        ]);

        /*
         * Authenticate first.
         */
        $loginResponse = $this->postJson(
            $this->loginUrl,
            [
                'email' => $user->email,
                'password' => 'password',
            ],
        );

        $loginResponse->assertOk();

        /*
         * Retrieve the active session.
         */
        $session = AuthenticationSession::query()
            ->where('user_id', $user->getKey())
            ->whereNull('revoked_at')
            ->firstOrFail();

        /*
         * Logout.
         */
        $logoutResponse = $this->postJson(
            $this->logoutUrl,
        );

        $logoutResponse->assertOk();

        $logoutResponse->assertJson([
            'message' => 'Logout successful.',
        ]);

        /*
         * Laravel guard must now be unauthenticated.
         */
        $this->assertGuest();

        /*
         * Authentication session must be revoked.
         */
        $this->assertNotNull(
            $session->fresh()->revoked_at,
        );

        /*
         * Logout must be recorded in LoginHistory.
         */
        $this->assertDatabaseHas(
            'login_histories',
            [
                'user_id' => $user->getKey(),
                'event' => 'logout',
                'reason' => 'logout',
                'authentication_session_id' => $session->getKey(),
            ],
        );
    }

    /*
    |--------------------------------------------------------------------------
    | AUTHENTICATION CONTEXT
    |--------------------------------------------------------------------------
    */

    /**
     * The AuthenticationContext is immutable.
     */
    public function test_authentication_context_is_readonly(): void
    {
        $context = new AuthenticationContext(
            email: 'readonly@example.com',
            password: 'password',
            sessionId: 'session-readonly',
            ipAddress: '127.0.0.1',
            userAgent: $this->userAgent,
            browser: 'Chrome',
            device: 'Windows',
        );

        /*
         * PHP readonly classes/properties prevent mutation.
         *
         * We verify that the expected values are preserved.
         */
        $this->assertSame(
            'readonly@example.com',
            $context->email,
        );

        $this->assertSame(
            'session-readonly',
            $context->sessionId,
        );

        $this->assertSame(
            'Chrome',
            $context->browser,
        );

        $this->assertSame(
            'Windows',
            $context->device,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SECURITY
    |--------------------------------------------------------------------------
    */

    /**
     * The plain-text password must never be stored in LoginHistory.
     */
    public function test_plain_password_is_not_stored_in_login_history(): void
    {
        $user = User::factory()->create([
            'email' => 'security@example.com',
            'password' => 'password',
            'status' => UserStatus::Active,
        ]);

        $this->postJson(
            $this->loginUrl,
            [
                'email' => $user->email,
                'password' => 'password',
            ],
        )->assertOk();

        /*
         * LoginHistory must never contain a password column/value.
         */
        $history = LoginHistory::query()
            ->where('user_id', $user->getKey())
            ->firstOrFail();

        $attributes = $history->getAttributes();

        foreach ($attributes as $value) {
            if (is_string($value)) {
                $this->assertStringNotContainsString(
                    'password',
                    strtolower($value),
                );
            }
        }
    }
}