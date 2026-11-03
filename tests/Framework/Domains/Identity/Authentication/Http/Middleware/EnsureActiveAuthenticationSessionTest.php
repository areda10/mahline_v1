<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Authentication\Http\Middleware;

use App\Domains\Identity\Authentication\Http\Middleware\EnsureActiveAuthenticationSession;
use App\Domains\Identity\Authentication\Models\AuthenticationSession;
use App\Domains\Identity\Authentication\Services\SessionService;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

final class EnsureActiveAuthenticationSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_authentication_session_allows_request(): void
    {
        $user = User::factory()->create();
        [$request] = $this->authenticatedRequest($user);

        $response = app(EnsureActiveAuthenticationSession::class)->handle(
            $request,
            static fn () => response('allowed'),
        );

        self::assertSame('allowed', $response->getContent());
        self::assertTrue(Auth::check());
    }

    public function test_revoked_authentication_session_logs_user_out_and_redirects(): void
    {
        $user = User::factory()->create();
        [$request, $session] = $this->authenticatedRequest($user);

        app(SessionService::class)->revoke($session, 'user_deactivated');

        $response = app(EnsureActiveAuthenticationSession::class)->handle(
            $request,
            static fn () => response('must not be reached'),
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame(route('authentication.login'), $response->headers->get('Location'));
        self::assertFalse(Auth::check());
    }

    public function test_authenticated_user_without_authentication_session_is_rejected(): void
    {
        $user = User::factory()->create();
        [$request] = $this->authenticatedRequest($user, false);

        $response = app(EnsureActiveAuthenticationSession::class)->handle(
            $request,
            static fn () => response('must not be reached'),
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertFalse(Auth::check());
    }

    public function test_authentication_session_must_match_current_laravel_session(): void
    {
        $user = User::factory()->create();
        [$request, $session] = $this->authenticatedRequest($user);

        $request->session()->setId('another-laravel-session');

        $response = app(EnsureActiveAuthenticationSession::class)->handle(
            $request,
            static fn () => response('must not be reached'),
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertFalse(Auth::check());
        self::assertNull($session->fresh()->revoked_at);
    }

    /**
     * @return array{0: Request, 1: AuthenticationSession|null}
     */
    private function authenticatedRequest(
        User $user,
        bool $withAuthenticationSession = true,
    ): array {
        $store = app('session.store');
        $store->start();

        // Mirror AuthenticationWorkflow: authenticate, regenerate the
        // Laravel session identifier, then persist the MAHLINE session.
        Auth::login($user);
        $store->regenerate();

        $authenticationSession = $withAuthenticationSession
            ? app(SessionService::class)->create(
                user: $user,
                sessionId: $store->getId(),
            )
            : null;

        if ($authenticationSession !== null) {
            $store->put(
                'authentication_session_id',
                $authenticationSession->getKey(),
            );
        }

        $request = Request::create('/back-office');
        $request->setLaravelSession($store);
        $request->setUserResolver(static fn () => $user);

        return [$request, $authenticationSession];
    }
}
