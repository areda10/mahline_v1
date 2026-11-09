<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Identity\Authentication\Http\Middleware;

use App\Domains\Identity\Authentication\Http\Middleware\EnsureActiveAuthenticationSession;
use App\Domains\Identity\Authentication\Models\AuthenticationSession;
use App\Domains\Identity\Authentication\Services\SessionService;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class EnsureActiveAuthenticationSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_session_is_invalidated_and_redirected_to_login(): void
    {
        $this->travelTo(now()->startOfSecond());

        $user = $this->userWithRole('super_admin_root');
        $store = $this->sessionStore();
        $session = $this->createAuthenticationSession($user, $store);

        $session->forceFill([
            'last_activity_at' => now()->subMinutes(15),
        ])->save();

        $request = $this->requestFor($user, $store, $session);
        $response = $this->middleware()->handle(
            $request,
            static fn (): Response => new Response('protected'),
        );

        $this->assertTrue($response->isRedirect(route('authentication.login')));
        $this->assertNull($store->get('authentication_session_id'));
        $this->assertSame(
            'inactivity_timeout',
            $session->fresh()->revocation_reason,
        );
    }

    public function test_revoked_session_cannot_continue_to_protected_route(): void
    {
        $user = $this->userWithRole('user');
        $store = $this->sessionStore();
        $session = $this->createAuthenticationSession($user, $store);

        app(SessionService::class)->revoke($session, 'security_review');

        $request = $this->requestFor($user, $store, $session);
        $response = $this->middleware()->handle(
            $request,
            static fn (): Response => new Response('must not run'),
        );

        $this->assertTrue($response->isRedirect(route('authentication.login')));
        $this->assertNull($store->get('authentication_session_id'));
        $this->assertSame('security_review', $session->fresh()->revocation_reason);
    }

    public function test_valid_session_is_touched_and_request_continues(): void
    {
        $this->travelTo(now()->startOfSecond());

        $user = $this->userWithRole('user');
        $store = $this->sessionStore();
        $session = $this->createAuthenticationSession($user, $store);
        $previousActivity = now()->subMinutes(5);

        $session->forceFill([
            'last_activity_at' => $previousActivity,
        ])->save();

        $request = $this->requestFor($user, $store, $session);
        $response = $this->middleware()->handle(
            $request,
            static fn (): Response => new Response('protected', 200),
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('protected', $response->getContent());
        $this->assertTrue(
            $session->fresh()->last_activity_at->equalTo(now()),
        );
        $this->assertNull($session->fresh()->revoked_at);
    }

    private function middleware(): EnsureActiveAuthenticationSession
    {
        return new EnsureActiveAuthenticationSession(
            app(SessionService::class),
        );
    }

    private function userWithRole(string $slug): User
    {
        $user = User::factory()->create();

        $role = Role::query()->firstOrCreate(
            ['slug' => $slug],
            [
                'name' => str($slug)->replace('_', ' ')->title()->toString(),
                'is_active' => true,
            ],
        );

        $user->roles()->syncWithoutDetaching([$role->getKey()]);

        return $user->fresh();
    }

    private function sessionStore(): Store
    {
        $store = new Store('mahline-test-session', new ArraySessionHandler(120));
        $store->start();

        return $store;
    }

    private function createAuthenticationSession(User $user, Store $store): AuthenticationSession
    {
        return app(SessionService::class)->create(
            user: $user,
            sessionId: $store->getId(),
            ipAddress: '127.0.0.1',
            userAgent: 'PHPUnit',
            browser: 'Test Browser',
            device: 'Test Device',
        );
    }

    private function requestFor(
        User $user,
        Store $store,
        AuthenticationSession $authenticationSession,
    ): Request {
        $store->put(
            'authentication_session_id',
            (string) $authenticationSession->getKey(),
        );

        $request = Request::create('/protected', 'GET');
        $request->setLaravelSession($store);
        $request->setUserResolver(static fn (): User => $user);

        Auth::setUser($user);

        return $request;
    }
}
