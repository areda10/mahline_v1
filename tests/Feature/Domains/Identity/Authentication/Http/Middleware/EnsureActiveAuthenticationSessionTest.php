<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Identity\Authentication\Http\Middleware;

use App\Domains\Identity\Authentication\Http\Middleware\EnsureActiveAuthenticationSession;
use App\Domains\Identity\Authentication\Models\AuthenticationSession;
use App\Domains\Identity\Authentication\Services\SessionService;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class EnsureActiveAuthenticationSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_session_is_invalidated_and_redirected_to_login(): void
    {
        $user = User::factory()->create();
        $store = $this->sessionStore();
        $authenticationSession = new AuthenticationSession([
            'session_id' => $store->getId(),
        ]);

        $service = Mockery::mock(SessionService::class);
        $service->shouldReceive('findActiveById')
            ->once()
            ->with($user, 'authentication-session-id')
            ->andReturn($authenticationSession);
        $service->shouldReceive('expireIfInactive')
            ->once()
            ->with($user, $authenticationSession)
            ->andReturn(true);
        $service->shouldNotReceive('touch');

        Auth::shouldReceive('logout')->once();

        $store->put('authentication_session_id', 'authentication-session-id');
        $request = $this->requestFor($user, $store);

        $response = (new EnsureActiveAuthenticationSession($service))->handle(
            $request,
            static fn (): Response => new Response('protected'),
        );

        $this->assertTrue($response->isRedirect(route('authentication.login')));
        $this->assertNull($store->get('authentication_session_id'));
    }

    public function test_session_revoked_during_activity_refresh_is_not_allowed_to_continue(): void
    {
        $user = User::factory()->create();
        $store = $this->sessionStore();
        $authenticationSession = new AuthenticationSession([
            'session_id' => $store->getId(),
        ]);

        $service = Mockery::mock(SessionService::class);
        $service->shouldReceive('findActiveById')
            ->once()
            ->andReturn($authenticationSession);
        $service->shouldReceive('expireIfInactive')
            ->once()
            ->andReturn(false);
        $service->shouldReceive('touch')
            ->once()
            ->with($authenticationSession)
            ->andReturn(false);

        Auth::shouldReceive('logout')->once();

        $request = $this->requestFor($user, $store);
        $response = (new EnsureActiveAuthenticationSession($service))->handle(
            $request,
            static fn (): Response => new Response('must not run'),
        );

        $this->assertTrue($response->isRedirect(route('authentication.login')));
        $this->assertNull($store->get('authentication_session_id'));
    }

    public function test_valid_session_is_touched_and_request_continues(): void
    {
        $user = User::factory()->create();
        $store = $this->sessionStore();
        $authenticationSession = new AuthenticationSession([
            'session_id' => $store->getId(),
        ]);

        $service = Mockery::mock(SessionService::class);
        $service->shouldReceive('findActiveById')
            ->once()
            ->andReturn($authenticationSession);
        $service->shouldReceive('expireIfInactive')
            ->once()
            ->andReturn(false);
        $service->shouldReceive('touch')
            ->once()
            ->with($authenticationSession)
            ->andReturn(true);

        $request = $this->requestFor($user, $store);
        $response = (new EnsureActiveAuthenticationSession($service))->handle(
            $request,
            static fn (): Response => new Response('protected', 200),
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('protected', $response->getContent());
    }

    private function sessionStore(): Store
    {
        $store = new Store('mahline-test-session', new ArraySessionHandler(120));
        $store->start();

        return $store;
    }

    private function requestFor(User $user, Store $store): Request
    {
        $request = Request::create('/protected', 'GET');
        $request->setLaravelSession($store);
        $request->setUserResolver(static fn (): User => $user);

        return $request;
    }
}
