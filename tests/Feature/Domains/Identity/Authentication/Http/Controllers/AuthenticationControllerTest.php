<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Identity\Authentication\Http\Controllers;

use App\Domains\Identity\Authentication\Models\AuthenticationSession;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
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

    public function test_guest_cannot_logout(): void //ok
    {
        $response = $this->postJson(
            '/authentication/logout',
        );

        $response->assertStatus(401);

        $response->assertJson([
            'message' => 'Unauthenticated.',
        ]);
    }

    public function test_authentication_request_validates_required_fields(): void //ok
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
    public function test_invalid_email_is_rejected(): void //ok
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

    public function test_authenticated_user_can_logout(): void
    {
        config()->set('session.driver', 'database');

        $user = User::factory()->create([
            'email' => 'logout@example.com',
            'password' => 'password',
            'status' => UserStatus::Active,
        ]);

        /*
        * ---------------------------------------------------------
        * 1. Login through the real HTTP workflow.
        * ---------------------------------------------------------
        */
        $loginResponse = $this
            ->withHeader('User-Agent', $this->userAgent)
            ->postJson($this->loginUrl, [
                'email' => $user->email,
                'password' => 'password',
            ]);

        $loginResponse->assertOk();

        $this->assertAuthenticatedAs($user);

        /*
        * ---------------------------------------------------------
        * 2. Retrieve the domain authentication session.
        * ---------------------------------------------------------
        */
        $authenticationSession = AuthenticationSession::query()
            ->where('user_id', $user->getKey())
            ->whereNull('revoked_at')
            ->latest('authenticated_at')
            ->firstOrFail();

        /*
        * ---------------------------------------------------------
        * 3. Retrieve the Laravel session created by login.
        * ---------------------------------------------------------
        */
        $loginLaravelSessionId = $this->app['session']->getId();

        $this->assertSame(
            $loginLaravelSessionId,
            $authenticationSession->session_id,
        );

        /*
        * ---------------------------------------------------------
        * 4. Retrieve the actual session cookie returned by Laravel.
        * ---------------------------------------------------------
        */
        $sessionCookie = collect(
            $loginResponse->headers->getCookies()
        )->first(
            fn ($cookie) =>
                $cookie->getName() === $this->app['session']->getName()
        );

        $this->assertNotNull($sessionCookie);

        /*
        * Laravel encrypts the session cookie.
        *
        * The browser sends the encrypted cookie value back.
        * Therefore we must reuse the cookie value returned by
        * the login response, not the raw session ID.
        */
        $cookieValue = $sessionCookie->getValue();

        $this->assertNotEmpty($cookieValue);

        /*
        * ---------------------------------------------------------
        * 5. Diagnostic check before logout.
        * ---------------------------------------------------------
        */
        // dump([
        //     'login_laravel_session_id' => $loginLaravelSessionId,
        //     'authentication_session_id' => $authenticationSession->session_id,
        //     'session_cookie_name' => $sessionCookie->getName(),
        //     'session_cookie_value_length' => strlen($cookieValue),
        //     'auth_user_before_logout' => Auth::id(),
        // ]);

        /*
        * ---------------------------------------------------------
        * 6. Send logout with the exact browser cookie.
        * ---------------------------------------------------------
        */
        $logoutResponse = $this
            ->withCookie(
                $sessionCookie->getName(),
                $cookieValue,
            )
            ->postJson($this->logoutUrl);

        /*
        * ---------------------------------------------------------
        * 7. Diagnostic check if logout fails.
        * ---------------------------------------------------------
        */
        if ($logoutResponse->status() !== 200) {
            dump([
                'logout_status' => $logoutResponse->status(),
                'logout_response' => $logoutResponse->json(),
                'auth_user_after_logout_request' => Auth::id(),
                'laravel_session_id_after_logout_request' =>
                    $this->app['session']->getId(),
                'authentication_session_id' =>
                    $authenticationSession->fresh()?->session_id,
                'authentication_session_revoked_at' =>
                    $authenticationSession->fresh()?->revoked_at,
            ]);
        }

        /*
        * ---------------------------------------------------------
        * 8. Logout must succeed.
        * ---------------------------------------------------------
        */
        $logoutResponse->assertOk()
            ->assertJson([
                'message' => 'Logout successful.',
            ]);

        /*
        * ---------------------------------------------------------
        * 9. Laravel authentication must be cleared.
        * ---------------------------------------------------------
        */
        $this->assertGuest();

        /*
        * ---------------------------------------------------------
        * 10. The current authentication session must be revoked.
        * ---------------------------------------------------------
        */
        $authenticationSession->refresh();

        $this->assertNotNull(
            $authenticationSession->revoked_at,
        );

        $this->assertSame(
            'logout',
            $authenticationSession->revocation_reason,
        );

        /*
        * ---------------------------------------------------------
        * 11. Logout history must reference this session.
        * ---------------------------------------------------------
        */
        $this->assertDatabaseHas(
            'login_histories',
            [
                'user_id' => $user->getKey(),
                'event' => 'logout',
                'reason' => 'logout',
                'authentication_session_id' =>
                    $authenticationSession->getKey(),
            ],
        );
    }

    public function test_user_can_authenticate_successfully(): void //ok
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

}