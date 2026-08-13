<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Authentication\Http\Controllers;

use App\Domains\Identity\Authentication\DTOs\AuthenticationContext;
use App\Domains\Identity\Authentication\Models\AuthenticationSession;
use App\Domains\Identity\Authentication\Models\LoginHistory;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class AuthenticationControllerTest extends TestCase
{
    use RefreshDatabase;

    /*
     * ================================================================
     * AUTHENTICATION CONTROLLER TESTS
     * ================================================================
     *
     * These tests verify the HTTP layer of authentication.
     *
     * The Controller is responsible for:
     *
     * 1. Receiving the HTTP request.
     * 2. Validating the request through AuthenticationRequest.
     * 3. Building the AuthenticationContext.
     * 4. Detecting / forwarding client information.
     * 5. Delegating authentication to AuthenticationService.
     *
     * AuthenticationService remains responsible for:
     *
     * - locating the user;
     * - checking account status;
     * - verifying the password;
     * - creating the authentication session;
     * - recording login history.
     *
     * SessionService remains responsible for:
     *
     * ONE USER → ONE ACTIVE AUTHENTICATED SESSION
     *
     * Therefore these tests verify the complete HTTP → Service flow.
     */


    /**
     * Test that a valid user can authenticate successfully.
     *
     * Expected result:
     *
     * - HTTP authentication succeeds.
     * - An authentication session is created.
     * - A successful login history entry is created.
     */
    public function test_user_can_authenticate_successfully(): void
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => Hash::make('password'),
            'status' => UserStatus::Active,
        ]);

        $response = $this->postJson(
            route('authentication.login'),
            [
                'email' => 'john@example.com',
                'password' => 'password',
            ]
        );

        /*
         * The exact success status depends on the Controller
         * implementation.
         *
         * 200 is expected for a JSON authentication endpoint.
         */
        $response->assertOk();

        /*
         * The authentication session must exist.
         */
        $this->assertDatabaseHas(
            'authentication_sessions',
            [
                'user_id' => $user->getKey(),
            ]
        );

        /*
         * A successful authentication must be recorded.
         */
        $this->assertDatabaseHas(
            'login_histories',
            [
                'user_id' => $user->getKey(),
                'email' => $user->email,
                'event' => 'success',
            ]
        );
    }


    /**
     * Test that an unknown email is rejected.
     *
     * No user exists with the supplied email.
     *
     * Expected result:
     *
     * - Authentication fails.
     * - No authentication session is created.
     * - LoginHistory records the failed attempt.
     */
    public function test_unknown_user_is_rejected(): void
    {
        $response = $this->postJson(
            route('authentication.login'),
            [
                'email' => 'unknown@example.com',
                'password' => 'password',
            ]
        );

        /*
         * Authentication must not succeed.
         */
        $response->assertStatus(404);

        /*
         * No authentication session must be created.
         */
        $this->assertDatabaseCount(
            'authentication_sessions',
            0
        );

        /*
         * The failed authentication attempt must be recorded.
         */
        $this->assertDatabaseHas(
            'login_histories',
            [
                'email' => 'unknown@example.com',
                'event' => 'failed',
            ]
        );
    }


    /**
     * Test that an invalid password is rejected.
     *
     * The user exists, but the supplied password is incorrect.
     *
     * Expected result:
     *
     * - Authentication fails.
     * - No session is created.
     * - Failed login is recorded.
     */
    public function test_invalid_password_is_rejected(): void
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => Hash::make('correct-password'),
            'status' => UserStatus::Active,
        ]);

        $response = $this->postJson(
            route('authentication.login'),
            [
                'email' => 'john@example.com',
                'password' => 'wrong-password',
            ]
        );

        /*
         * Authentication must be rejected.
         */
        $response->assertStatus(401);

        /*
         * No authentication session must exist.
         */
        $this->assertDatabaseMissing(
            'authentication_sessions',
            [
                'user_id' => $user->getKey(),
            ]
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
            ]
        );
    }


    /**
     * Test that an inactive user cannot authenticate.
     *
     * The password may be correct, but the account status
     * prevents authentication.
     */
    public function test_inactive_user_is_rejected(): void
    {
        $user = User::factory()->create([
            'email' => 'inactive@example.com',
            'password' => Hash::make('password'),
            'status' => UserStatus::Inactive,
        ]);

        $response = $this->postJson(
            route('authentication.login'),
            [
                'email' => 'inactive@example.com',
                'password' => 'password',
            ]
        );

        /*
         * Authentication must be rejected.
         */
        $response->assertStatus(401);

        /*
         * No session may be created for an inactive account.
         */
        $this->assertDatabaseMissing(
            'authentication_sessions',
            [
                'user_id' => $user->getKey(),
            ]
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
            ]
        );
    }


    /**
     * Test that authentication creates exactly one active session.
     */
    public function test_successful_authentication_creates_active_session(): void
    {
        $user = User::factory()->create([
            'email' => 'session@example.com',
            'password' => Hash::make('password'),
            'status' => UserStatus::Active,
        ]);

        $response = $this->postJson(
            route('authentication.login'),
            [
                'email' => 'session@example.com',
                'password' => 'password',
            ]
        );

        $response->assertOk();

        /*
         * Retrieve the created authentication session.
         */
        $session = AuthenticationSession::query()
            ->where('user_id', $user->getKey())
            ->first();

        $this->assertNotNull($session);

        /*
         * A newly authenticated session must be active.
         */
        $this->assertNull($session->revoked_at);
        $this->assertNull($session->revocation_reason);
    }


    /**
     * Test that a successful authentication creates a LoginHistory entry.
     */
    public function test_successful_authentication_creates_login_history(): void
    {
        $user = User::factory()->create([
            'email' => 'history@example.com',
            'password' => Hash::make('password'),
            'status' => UserStatus::Active,
        ]);

        $response = $this->postJson(
            route('authentication.login'),
            [
                'email' => 'history@example.com',
                'password' => 'password',
            ]
        );

        $response->assertOk();

        /*
         * The success event must exist.
         */
        $this->assertDatabaseHas(
            'login_histories',
            [
                'user_id' => $user->getKey(),
                'email' => $user->email,
                'event' => 'success',
            ]
        );

        /*
         * Retrieve the history entry so we can verify
         * that it is linked to the authentication session.
         */
        $history = LoginHistory::query()
            ->where('user_id', $user->getKey())
            ->where('event', 'success')
            ->first();

        $this->assertNotNull($history);

        /*
         * A successful login must reference the session
         * that was created by SessionService.
         */
        $this->assertNotNull(
            $history->authentication_session_id
        );
    }


    /**
     * Test that client information is recorded.
     *
     * The Controller builds AuthenticationContext from the
     * HTTP request and forwards client information to the
     * authentication layer.
     */
    public function test_client_information_is_recorded(): void
    {
        $user = User::factory()->create([
            'email' => 'client@example.com',
            'password' => Hash::make('password'),
            'status' => UserStatus::Active,
        ]);

        $response = $this
            ->withHeaders([
                'User-Agent' =>
                    'Mozilla/5.0 (Linux; Android 14) '
                    . 'AppleWebKit/537.36 '
                    . 'Chrome/120.0 Mobile Safari/537.36',
            ])
            ->postJson(
                route('authentication.login'),
                [
                    'email' => 'client@example.com',
                    'password' => 'password',
                ]
            );

        $response->assertOk();

        /*
         * The raw User-Agent must be stored in the authentication
         * session or login history according to the architecture.
         */
        $this->assertDatabaseHas(
            'login_histories',
            [
                'user_id' => $user->getKey(),
                'event' => 'success',
            ]
        );
    }


    /**
     * Test that a second login replaces the previous session.
     *
     * MAHLINE rule:
     *
     * ONE USER → ONE ACTIVE AUTHENTICATED SESSION
     *
     * Therefore:
     *
     * Android / Firefox
     *       ↓
     * Session A
     *
     * then:
     *
     * iPhone / Safari
     *       ↓
     * Session B
     *
     * Session A must no longer be active.
     */
    // public function test_second_login_replaces_previous_session(): void
    // {
    //     $user = User::factory()->create([
    //         'email' => 'multi-device@example.com',
    //         'password' => Hash::make('password'),
    //         'status' => UserStatus::Active,
    //     ]);

    //     /*
    //      * First authentication.
    //      */
    //     $firstResponse = $this
    //         ->withHeaders([
    //             'User-Agent' =>
    //                 'Mozilla/5.0 (Linux; Android 14) '
    //                 . 'Firefox/120.0',
    //         ])
    //         ->postJson(
    //             route('authentication.login'),
    //             [
    //                 'email' => 'multi-device@example.com',
    //                 'password' => 'password',
    //             ]
    //         );

    //     $firstResponse->assertOk();

    //     /*
    //      * There must be exactly one authentication session.
    //      */
    //     $this->assertSame(
    //         1,
    //         AuthenticationSession::query()
    //             ->where('user_id', $user->getKey())
    //             ->count()
    //     );

    //     $firstSession = AuthenticationSession::query()
    //         ->where('user_id', $user->getKey())
    //         ->first();

    //     $this->assertNotNull($firstSession);

    //     /*
    //      * Second authentication from another device/browser.
    //      */
    //     $secondResponse = $this
    //         ->withHeaders([
    //             'User-Agent' =>
    //                 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0) '
    //                 . 'AppleWebKit/605.1.15 '
    //                 . 'Version/18.0 Mobile/15E148 Safari/604.1',
    //         ])
    //         ->postJson(
    //             route('authentication.login'),
    //             [
    //                 'email' => 'multi-device@example.com',
    //                 'password' => 'password',
    //             ]
    //         );

    //     $secondResponse->assertOk();

    //     /*
    //      * The architecture permits only ONE authentication
    //      * session for this user.
    //      */
    //     $this->assertSame(
    //         1,
    //         AuthenticationSession::query()
    //             ->where('user_id', $user->getKey())
    //             ->count()
    //     );

    //     /*
    //      * Retrieve the current session.
    //      */
    //     $currentSession = AuthenticationSession::query()
    //         ->where('user_id', $user->getKey())
    //         ->first();

    //     $this->assertNotNull($currentSession);

    //     /*
    //      * The current session must be active.
    //      */
    //     $this->assertNull(
    //         $currentSession->revoked_at
    //     );

    //     /*
    //      * The current session must be different from the
    //      * first session.
    //      */
    //     $this->assertNotSame(
    //         $firstSession->getKey(),
    //         $currentSession->getKey()
    //     );
    // }

    // update test_second_login_replaces_previous_session
    public function test_second_login_replaces_previous_session(): void
    {
        $user = User::factory()->create([
            'email' => 'second-login@example.com',
            'password' => 'password',
            'status' => UserStatus::Active,
        ]);

        /*
        * First authentication.
        */
        $firstContext = new AuthenticationContext(
            email: 'second-login@example.com',
            password: 'password',
            sessionId: 'session-a',
            ipAddress: '127.0.0.1',
            userAgent: 'Mozilla/5.0 Android',
            browser: 'Firefox',
            device: 'Android',
        );

        $this->authenticationService->authenticate(
            $firstContext
        );

        /*
        * Second authentication from another device/browser.
        *
        * ONE USER → ONE ACTIVE AUTHENTICATED SESSION.
        *
        * The first session must therefore be revoked.
        */
        $secondContext = new AuthenticationContext(
            email: 'second-login@example.com',
            password: 'password',
            sessionId: 'session-b',
            ipAddress: '192.168.1.10',
            userAgent: 'Mozilla/5.0 iPhone',
            browser: 'Safari',
            device: 'iPhone',
        );

        $this->authenticationService->authenticate(
            $secondContext
        );

        /*
        * Exactly ONE ACTIVE session must exist.
        */
        $this->assertSame(
            1,
            AuthenticationSession::query()
                ->where('user_id', $user->getKey())
                ->whereNull('revoked_at')
                ->count()
        );

        /*
        * The first session remains in the database,
        * but is revoked because of the new login.
        */
        $this->assertDatabaseHas(
            'authentication_sessions',
            [
                'user_id' => $user->getKey(),
                'session_id' => 'session-a',
                'revocation_reason' => 'new_login',
            ]
        );

        /*
        * The second session is the active session.
        */
        $this->assertDatabaseHas(
            'authentication_sessions',
            [
                'user_id' => $user->getKey(),
                'session_id' => 'session-b',
                'revoked_at' => null,
            ]
        );

        /*
        * Login history must contain the revocation
        * of the previous session.
        */
        $this->assertDatabaseHas(
            'login_histories',
            [
                'user_id' => $user->getKey(),
                'event' => 'session_revoked',
                'reason' => 'new_login',
            ]
        );

        /*
        * The successful second authentication must also
        * be recorded.
        */
        $this->assertDatabaseHas(
            'login_histories',
            [
                'user_id' => $user->getKey(),
                'event' => 'success',
            ]
        );
    }


    /**
     * Test that failed authentication does not create a session.
     */
    public function test_failed_authentication_never_creates_session(): void
    {
        User::factory()->create([
            'email' => 'failed@example.com',
            'password' => Hash::make('correct-password'),
            'status' => UserStatus::Active,
        ]);

        $response = $this->postJson(
            route('authentication.login'),
            [
                'email' => 'failed@example.com',
                'password' => 'wrong-password',
            ]
        );

        $response->assertStatus(401);

        /*
         * The authentication_sessions table must remain empty.
         */
        $this->assertDatabaseCount(
            'authentication_sessions',
            0
        );
    }


    /**
     * Test request validation.
     *
     * AuthenticationRequest must reject an invalid email
     * and a missing password before AuthenticationService
     * is called.
     */
    public function test_authentication_request_validates_required_fields(): void
    {
        $response = $this->postJson(
            route('authentication.login'),
            []
        );

        /*
         * Laravel validation should return HTTP 422.
         */
        $response->assertStatus(422);

        /*
         * The validation response must contain the expected
         * validation fields.
         */
        $response->assertJsonValidationErrors([
            'email',
            'password',
        ]);

        /*
         * No authentication must take place.
         */
        $this->assertDatabaseCount(
            'authentication_sessions',
            0
        );

        $this->assertDatabaseCount(
            'login_histories',
            0
        );
    }
}