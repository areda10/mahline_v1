<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Authentication\Http\Middleware;

use App\Domains\Identity\Authentication\Http\Middleware\EnsureActiveAuthenticationSession;
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
        $session = app(SessionService::class)->create(
            user: $user,
            sessionId: 'middleware-active-session',
        );

        $request = $this->authenticatedRequest($user, $session->getKey());

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
        $sessions = app(SessionService::class);
        $session = $sessions->create(
            user: $user,
            sessionId: 'middleware-revoked-session',
        );

        $request = $this->authenticatedRequest($user, $session->getKey());

        $sessions->revoke($session, 'user_deactivated');

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
        $request = $this->authenticatedRequest($user, null);

        $response = app(EnsureActiveAuthenticationSession::class)->handle(
            $request,
            static fn () => response('must not be reached'),
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertFalse(Auth::check());
    }

    private function authenticatedRequest(User $user, ?string $sessionId): Request
    {
        $store = app('session.store');
        $store->start();

        if ($sessionId !== null) {
            $store->put('authentication_session_id', $sessionId);
        }

        Auth::login($user);

        $request = Request::create('/back-office');
        $request->setLaravelSession($store);
        $request->setUserResolver(static fn () => $user);

        return $request;
    }
}
