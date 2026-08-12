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

    /**
     * Authentication session service under test.
     */
    private SessionService $sessionService;

    /**
     * Prepare the test environment.
     */
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
        );

        /*
         * The returned object must be an authentication session.
         */
        $this->assertInstanceOf(
            AuthenticationSession::class,
            $session
        );

        /*
         * The session must belong to the authenticated user.
         */
        $this->assertSame(
            $user->getKey(),
            $session->user_id
        );

        /*
         * The Laravel session identifier must be stored.
         */
        $this->assertSame(
            'session-a',
            $session->session_id
        );

        /*
         * A newly created session must not be revoked.
         */
        $this->assertNull(
            $session->revoked_at
        );

        $this->assertNull(
            $session->revocation_reason
        );

        /*
         * The new session must be active.
         */
        $this->assertTrue(
            $this->sessionService->isActive($session)
        );
    }

    /**
     * A new login replaces the previous active session.
     *
     * Example:
     *
     * Android / Firefox
     *     Session A
     *
     * then
     *
     * iPhone / Safari
     *     Session B
     *
     * Result:
     *
     * Session A → revoked
     * Session B → active
     */
    public function test_new_login_replaces_previous_session(): void
    {
        $user = User::factory()->create();

        /*
         * First login.
         */
        $firstSession = $this->sessionService->create(
            user: $user,
            sessionId: 'session-a',
        );

        $firstSessionId = $firstSession->getKey();

        /*
         * Second login from another device/browser.
         */
        $secondSession = $this->sessionService->create(
            user: $user,
            sessionId: 'session-b',
        );

        /*
         * A new authentication session must have a new ULID.
         */
        $this->assertNotSame(
            $firstSessionId,
            $secondSession->getKey()
        );

        /*
         * The new session must use the new Laravel session ID.
         */
        $this->assertSame(
            'session-b',
            $secondSession->session_id
        );

        /*
         * The new session is active.
         */
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
         * Refresh the first session from the database.
         */
        $firstSession->refresh();

        /*
         * The previous session must now be revoked.
         */
        $this->assertNotNull(
            $firstSession->revoked_at
        );

        /*
         * The reason must identify the replacement caused by
         * a new login.
         */
        $this->assertSame(
            'new_login',
            $firstSession->revocation_reason
        );

        /*
         * The previous session is no longer active.
         */
        $this->assertFalse(
            $this->sessionService->isActive($firstSession)
        );
    }

    /**
     * A user can have many historical sessions,
     * but only one active session.
     */
    public function test_only_one_active_authentication_session_exists_for_user(): void
    {
        $user = User::factory()->create();

        /*
         * First login.
         */
        $firstSession = $this->sessionService->create(
            user: $user,
            sessionId: 'session-a',
        );

        /*
         * Second login.
         */
        $secondSession = $this->sessionService->create(
            user: $user,
            sessionId: 'session-b',
        );

        /*
         * Third login.
         */
        $thirdSession = $this->sessionService->create(
            user: $user,
            sessionId: 'session-c',
        );

        /*
         * All historical sessions remain in the database.
         */
        $this->assertSame(
            3,
            AuthenticationSession::withTrashed()
                ->where('user_id', $user->getKey())
                ->count()
        );

        /*
         * Only one session may remain active.
         */
        $this->assertSame(
            1,
            AuthenticationSession::query()
                ->where('user_id', $user->getKey())
                ->whereNull('revoked_at')
                ->count()
        );

        /*
         * The first session is revoked.
         */
        $firstSession->refresh();

        $this->assertFalse(
            $this->sessionService->isActive($firstSession)
        );

        /*
         * The second session is revoked.
         */
        $secondSession->refresh();

        $this->assertFalse(
            $this->sessionService->isActive($secondSession)
        );

        /*
         * The third and latest session is active.
         */
        $thirdSession->refresh();

        $this->assertTrue(
            $this->sessionService->isActive($thirdSession)
        );
    }

    /**
     * A previous session is revoked with the "new_login" reason.
     */
    public function test_previous_session_is_revoked_with_new_login_reason(): void
    {
        $user = User::factory()->create();

        /*
         * First authentication.
         */
        $firstSession = $this->sessionService->create(
            user: $user,
            sessionId: 'session-a',
        );

        /*
         * Second authentication.
         */
        $secondSession = $this->sessionService->create(
            user: $user,
            sessionId: 'session-b',
        );

        /*
         * Reload the first session.
         */
        $firstSession->refresh();

        /*
         * The first session must have been revoked.
         */
        $this->assertNotNull(
            $firstSession->revoked_at
        );

        $this->assertSame(
            'new_login',
            $firstSession->revocation_reason
        );

        /*
         * The first session must no longer be active.
         */
        $this->assertFalse(
            $this->sessionService->isActive($firstSession)
        );

        /*
         * The second session must remain active.
         */
        $this->assertTrue(
            $this->sessionService->isActive($secondSession)
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

        /*
         * Logout.
         */
        $result = $this->sessionService->revokeForUser(
            user: $user,
            reason: 'logout',
        );

        $this->assertTrue($result);

        /*
         * Reload the session.
         */
        $session->refresh();

        /*
         * The session must be revoked.
         */
        $this->assertNotNull(
            $session->revoked_at
        );

        $this->assertSame(
            'logout',
            $session->revocation_reason
        );

        /*
         * The session must no longer be active.
         */
        $this->assertFalse(
            $this->sessionService->isActive($session)
        );
    }

    /**
     * A specific authentication session can be revoked.
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
     * An already revoked session cannot be revoked again.
     */
    public function test_already_revoked_session_cannot_be_revoked_again(): void
    {
        $user = User::factory()->create();

        $session = $this->sessionService->create(
            user: $user,
            sessionId: 'session-a',
        );

        /*
         * First revocation.
         */
        $this->assertTrue(
            $this->sessionService->revoke(
                session: $session,
                reason: 'logout',
            )
        );

        /*
         * Second revocation must be rejected.
         */
        $this->assertFalse(
            $this->sessionService->revoke(
                session: $session,
                reason: 'security',
            )
        );

        /*
         * The original reason must remain unchanged.
         */
        $session->refresh();

        $this->assertSame(
            'logout',
            $session->revocation_reason
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

        /*
         * Give the timestamp enough time to change.
         */
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
     * A revoked session cannot update its last activity timestamp.
     */
    public function test_revoked_session_cannot_update_last_activity(): void
    {
        $user = User::factory()->create();

        $session = $this->sessionService->create(
            user: $user,
            sessionId: 'session-a',
        );

        /*
         * Revoke the session.
         */
        $this->sessionService->revoke(
            session: $session,
            reason: 'logout',
        );

        /*
         * A revoked session must reject activity updates.
         */
        $result = $this->sessionService->touch($session);

        $this->assertFalse($result);
    }

    /**
     * The SessionService must not depend on HTTP Request.
     *
     * This keeps the domain/service layer independent
     * from the HTTP layer.
     */
    public function test_session_service_does_not_depend_on_request(): void
    {
        $reflection = new \ReflectionClass(
            SessionService::class
        );

        $constructor = $reflection->getConstructor();

        /*
         * No constructor means no dependency.
         */
        if ($constructor === null) {
            $this->assertTrue(true);

            return;
        }

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            /*
             * Untyped parameters are not an HTTP Request dependency.
             */
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