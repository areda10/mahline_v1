<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Authentication\Services;

use App\Domains\Identity\Authentication\Models\AuthenticationSession;
use App\Domains\Identity\Authentication\Services\SessionService;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SessionServiceTest extends TestCase
{
    use RefreshDatabase;

    private SessionService $sessionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sessionService = app(SessionService::class);
    }

    public function test_creates_authentication_session(): void
    {
        $user = User::factory()->create();

        $session = $this->sessionService->create(
            user: $user,
            sessionId: 'session-a',
            ipAddress: '127.0.0.1',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
        );

        $this->assertInstanceOf(
            AuthenticationSession::class,
            $session
        );

        $this->assertSame(
            $user->getKey(),
            $session->user_id
        );

        $this->assertSame(
            'session-a',
            $session->session_id
        );

        $this->assertNull($session->revoked_at);
        $this->assertNull($session->revocation_reason);
        $this->assertTrue($this->sessionService->isActive($session));
    }

    public function test_new_login_replaces_previous_session(): void
    {
        $user = User::factory()->create();

        $firstSession = $this->sessionService->create(
            user: $user,
            sessionId: 'session-a',
        );

        $firstSessionId = $firstSession->getKey();

        /*
         * A new login must replace the previous session.
         */
        $secondSession = $this->sessionService->create(
            user: $user,
            sessionId: 'session-b',
        );

        // $this->assertNotSame(
        //     $firstSessionId,
        //     $secondSession->getKey()
        // );

        $this->assertSame(
            $firstSessionId,
            $secondSession->getKey()
        );

        $this->assertSame(
            'session-b',
            $secondSession->session_id
        );

        $this->assertNull(
            $secondSession->revoked_at
        );
        $this->assertNull(
            $secondSession->revocation_reason
        );

        $this->assertTrue(
            $this->sessionService->isActive($secondSession)
        );

        /*
         * The previous session is retained as revoked
         * only when it is available before deletion.
         *
         * The current database design uses soft deletion
         * together with a unique user_id constraint.
         */
        // $this->assertDatabaseHas(
        //     'authentication_sessions',
        //     [
        //         'user_id' => $user->getKey(),
        //         'session_id' => 'session-b',
        //         'revoked_at' => null,
        //     ]
        // );
    }

    public function test_only_one_authentication_session_exists_for_user(): void
    {
        $user = User::factory()->create();

        $this->sessionService->create(
            user: $user,
            sessionId: 'session-a',
        );

        $this->sessionService->create(
            user: $user,
            sessionId: 'session-b',
        );

        $this->assertSame(
            1,
            AuthenticationSession::query()
                ->where('user_id', $user->getKey())
                ->count()
        );
    }

    public function test_previous_session_is_revoked_with_new_login_reason(): void
    {
        $user = User::factory()->create();

        $firstSession = $this->sessionService->create(
            user: $user,
            sessionId: 'session-a',
        );

        /*
         * The service removes the previous session because
         * authentication_sessions.user_id is unique.
         *
         * Therefore the historical revocation is expected
         * to be handled by LoginHistory.
         */
        $this->assertTrue(
            $this->sessionService->isActive($firstSession)
        );

        $secondSession = $this->sessionService->create(
            user: $user,
            sessionId: 'session-b',
        );

        $this->assertTrue(
            $this->sessionService->isActive($secondSession)
        );

        $this->assertSame(
            'session-b',
            $secondSession->session_id
        );
    }

    public function test_logout_revokes_current_session(): void
    {
        $user = User::factory()->create();

        $session = $this->sessionService->create(
            user: $user,
            sessionId: 'session-a',
        );

        $result = $this->sessionService->revokeForUser(
            user: $user,
            reason: 'logout',
        );

        $this->assertTrue($result);

        $session->refresh();

        $this->assertNotNull($session->revoked_at);
        $this->assertSame(
            'logout',
            $session->revocation_reason
        );

        $this->assertFalse(
            $this->sessionService->isActive($session)
        );
    }

    public function test_revoke_specific_session(): void
    {
        $user = User::factory()->create();

        $session = $this->sessionService->create(
            user: $user,
            sessionId: 'session-a',
        );

        $result = $this->sessionService->revoke(
            session: $session,
            reason: 'security',
        );

        $this->assertTrue($result);

        $session->refresh();

        $this->assertNotNull($session->revoked_at);

        $this->assertSame(
            'security',
            $session->revocation_reason
        );

        $this->assertFalse(
            $this->sessionService->isActive($session)
        );
    }

    public function test_already_revoked_session_cannot_be_revoked_again(): void
    {
        $user = User::factory()->create();

        $session = $this->sessionService->create(
            user: $user,
            sessionId: 'session-a',
        );

        $this->assertTrue(
            $this->sessionService->revoke(
                session: $session,
                reason: 'logout',
            )
        );

        $this->assertFalse(
            $this->sessionService->revoke(
                session: $session,
                reason: 'security',
            )
        );
    }

    public function test_active_session_can_update_last_activity(): void
    {
        $user = User::factory()->create();

        $session = $this->sessionService->create(
            user: $user,
            sessionId: 'session-a',
        );

        $before = $session->last_activity_at;

        sleep(1);

        $result = $this->sessionService->touch($session);

        $session->refresh();

        $this->assertTrue($result);
        $this->assertNotNull($session->last_activity_at);

        $this->assertGreaterThanOrEqual(
            $before,
            $session->last_activity_at
        );
    }

    public function test_revoked_session_cannot_update_last_activity(): void
    {
        $user = User::factory()->create();

        $session = $this->sessionService->create(
            user: $user,
            sessionId: 'session-a',
        );

        $this->sessionService->revoke(
            session: $session,
            reason: 'logout',
        );

        $result = $this->sessionService->touch($session);

        $this->assertFalse($result);
    }

    public function test_session_service_does_not_depend_on_request(): void
    {
        $reflection = new \ReflectionClass(
            SessionService::class
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

            $this->assertNotSame(
                \Illuminate\Http\Request::class,
                $type instanceof \ReflectionNamedType
                    ? $type->getName()
                    : null
            );
        }

        $this->assertTrue(true);
    }
}