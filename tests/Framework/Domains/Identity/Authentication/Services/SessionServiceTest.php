<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Authentication\Services;

use App\Domains\Identity\Authentication\Models\AuthenticationSession;
use App\Domains\Identity\Authentication\Models\LoginHistory;
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

    /**
     * A new authentication session can be created.
     */
    public function test_creates_authentication_session(): void
    {
        $user = User::factory()->create();

        $session = $this->sessionService->create(
            user: $user,
            sessionId: 'session-a',
            ipAddress: '127.0.0.1',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Android',
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

        $this->assertNull(
            $session->revoked_at
        );

        $this->assertNull(
            $session->revocation_reason
        );

        $this->assertTrue(
            $this->sessionService->isActive($session)
        );
    }

    /**
     * A new login revokes the previous active session.
     *
     * Example:
     *
     * Android / Firefox
     *      ↓
     * Session A
     *
     * iPhone / Safari login
     *      ↓
     * Session A → revoked
     * Session B → active
     */
    public function test_new_login_replaces_previous_session(): void
    {
        $user = User::factory()->create();

        $firstSession = $this->sessionService->create(
            user: $user,
            sessionId: 'session-a',
            browser: 'Firefox',
            device: 'Android',
        );

        $secondSession = $this->sessionService->create(
            user: $user,
            sessionId: 'session-b',
            browser: 'Safari',
            device: 'iPhone',
        );

        /*
         * The session IDs must be different.
         */
        $this->assertNotSame(
            $firstSession->getKey(),
            $secondSession->getKey()
        );

        /*
         * The first session must now be revoked.
         */
        $firstSession->refresh();

        $this->assertNotNull(
            $firstSession->revoked_at
        );

        $this->assertSame(
            'new_login',
            $firstSession->revocation_reason
        );

        /*
         * The second session must be active.
         */
        $this->assertNull(
            $secondSession->revoked_at
        );

        $this->assertTrue(
            $this->sessionService->isActive($secondSession)
        );

        /*
         * Only one active session must exist.
         */
        $this->assertSame(
            1,
            AuthenticationSession::query()
                ->where('user_id', $user->getKey())
                ->whereNull('revoked_at')
                ->count()
        );
    }

    /**
     * Historical sessions are retained.
     *
     * The old session is not deleted when a new login occurs.
     */
    public function test_previous_session_is_retained_as_revoked(): void
    {
        $user = User::factory()->create();

        $firstSession = $this->sessionService->create(
            user: $user,
            sessionId: 'session-a',
        );

        $this->sessionService->create(
            user: $user,
            sessionId: 'session-b',
        );

        /*
         * The old session still exists.
         */
        $this->assertDatabaseHas(
            'authentication_sessions',
            [
                'id' => $firstSession->getKey(),
                'session_id' => 'session-a',
                'revocation_reason' => 'new_login',
            ]
        );

        /*
         * The new session also exists.
         */
        $this->assertDatabaseHas(
            'authentication_sessions',
            [
                'user_id' => $user->getKey(),
                'session_id' => 'session-b',
                'revoked_at' => null,
            ]
        );
    }

    /**
     * Only one active authentication session can exist
     * for the same user.
     */
    public function test_only_one_active_authentication_session_exists_for_user(): void
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

        $this->sessionService->create(
            user: $user,
            sessionId: 'session-c',
        );

        /*
         * Three historical sessions exist.
         */
        $this->assertSame(
            3,
            AuthenticationSession::query()
                ->where('user_id', $user->getKey())
                ->count()
        );

        /*
         * But only one session is active.
         */
        $this->assertSame(
            1,
            AuthenticationSession::query()
                ->where('user_id', $user->getKey())
                ->whereNull('revoked_at')
                ->count()
        );

        /*
         * The last session must be the active one.
         */
        $this->assertDatabaseHas(
            'authentication_sessions',
            [
                'user_id' => $user->getKey(),
                'session_id' => 'session-c',
                'revoked_at' => null,
            ]
        );
    }

    /**
     * A new login creates a LoginHistory entry
     * for the previous session revocation.
     */
    public function test_previous_session_revocation_is_recorded_in_login_history(): void
    {
        $user = User::factory()->create();

        $firstSession = $this->sessionService->create(
            user: $user,
            sessionId: 'session-a',
        );

        $this->sessionService->create(
            user: $user,
            sessionId: 'session-b',
        );

        /*
         * The previous session must be revoked.
         */
        $this->assertDatabaseHas(
            'authentication_sessions',
            [
                'id' => $firstSession->getKey(),
                'revoked_at' => $firstSession
                    ->fresh()
                    ->revoked_at,
                'revocation_reason' => 'new_login',
            ]
        );

        /*
         * The security event must be recorded.
         */
        $this->assertDatabaseHas(
            'login_histories',
            [
                'user_id' => $user->getKey(),
                'email' => $user->email,
                'event' => 'session_revoked',
                'reason' => 'new_login',
                'authentication_session_id' => $firstSession->getKey(),
            ]
        );
    }

    /**
     * Logout revokes the current active session.
     */
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

        $this->assertNotNull(
            $session->revoked_at
        );

        $this->assertSame(
            'logout',
            $session->revocation_reason
        );

        $this->assertFalse(
            $this->sessionService->isActive($session)
        );

        /*
         * Logout must also be recorded in LoginHistory
         * as a session_revoked event.
         */
        $this->assertDatabaseHas(
            'login_histories',
            [
                'user_id' => $user->getKey(),
                'event' => 'session_revoked',
                'reason' => 'logout',
                'authentication_session_id' => $session->getKey(),
            ]
        );
    }

    /**
     * A specific active session can be revoked.
     */
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

        $this->assertNotNull(
            $session->revoked_at
        );

        $this->assertSame(
            'security',
            $session->revocation_reason
        );

        $this->assertFalse(
            $this->sessionService->isActive($session)
        );
    }

    /**
     * A revoked session cannot be revoked twice.
     */
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

    /**
     * An active session can update its last activity timestamp.
     */
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

        $this->assertNotNull(
            $session->last_activity_at
        );

        $this->assertGreaterThanOrEqual(
            $before,
            $session->last_activity_at
        );
    }

    /**
     * A revoked session cannot update its last activity.
     */
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

    /**
     * The current active session can be retrieved.
     */
    public function test_current_returns_active_session(): void
    {
        $user = User::factory()->create();

        $session = $this->sessionService->create(
            user: $user,
            sessionId: 'session-a',
        );

        $current = $this->sessionService->current($user);

        $this->assertNotNull($current);

        $this->assertSame(
            $session->getKey(),
            $current->getKey()
        );
    }

    /**
     * No current session exists after logout.
     */
    public function test_current_returns_null_when_no_active_session_exists(): void
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

        $this->assertNull(
            $this->sessionService->current($user)
        );
    }

    /**
     * SessionService must not depend on Laravel's HTTP Request.
     */
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