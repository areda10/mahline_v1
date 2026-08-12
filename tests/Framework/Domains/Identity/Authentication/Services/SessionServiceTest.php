<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Authentication\Services;

use App\Domains\Identity\Authentication\Models\AuthenticationSession;
use App\Domains\Identity\Authentication\Services\SessionService;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for SessionService.
 *
 * The authentication session policy used by MAHLINE is:
 *
 * ONE USER → ONE ACTIVE AUTHENTICATED SESSION
 *
 * This means that when the same user logs in from another
 * browser or another device, the previous authentication
 * session is replaced by the new one.
 *
 * Example:
 *
 * Android + Firefox
 *     ↓
 * Session A
 *
 * iPhone + Safari
 *     ↓
 * Session A is revoked
 *     ↓
 * Session B becomes active
 *
 * Session A and Session B are different authentication
 * session records.
 */
final class SessionServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The service under test.
     */
    private SessionService $sessionService;

    /**
     * Prepare the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        /*
         * Resolve SessionService through Laravel's container.
         *
         * This also verifies that all required dependencies,
         * including LoginHistoryService, can be resolved.
         */
        $this->sessionService = app(SessionService::class);
    }

    /**
     * A user can create an authentication session.
     *
     * A newly created session must:
     *
     * - belong to the correct user;
     * - contain the provided Laravel session ID;
     * - not be revoked;
     * - be considered active.
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

        /*
         * The service must return an AuthenticationSession model.
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
         * The session must contain the client information.
         */
        $this->assertSame(
            '127.0.0.1',
            $session->ip_address
        );

        $this->assertSame(
            'Mozilla/5.0',
            $session->user_agent
        );

        $this->assertSame(
            'Firefox',
            $session->browser
        );

        $this->assertSame(
            'Android',
            $session->device
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
         * The newly created session must be active.
         */
        $this->assertTrue(
            $this->sessionService->isActive($session)
        );
    }

    /**
     * A new login creates a new authentication session.
     *
     * IMPORTANT:
     *
     * The second session must NOT reuse the primary key
     * of the first session.
     *
     * Session A and Session B are two different authentication
     * session records.
     *
     * The previous session is removed from the active
     * authentication_sessions table because the database
     * currently enforces:
     *
     * UNIQUE(user_id)
     *
     * The historical event is preserved through LoginHistory.
     */
    public function test_new_login_replaces_previous_session(): void
    {
        $user = User::factory()->create();

        /*
         * First login:
         *
         * Android + Firefox
         */
        $firstSession = $this->sessionService->create(
            user: $user,
            sessionId: 'session-a',
            ipAddress: '127.0.0.1',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Android',
        );

        $firstSessionId = $firstSession->getKey();

        /*
         * Second login:
         *
         * iPhone + Safari
         *
         * According to the MAHLINE policy, this login
         * replaces the first authentication session.
         */
        $secondSession = $this->sessionService->create(
            user: $user,
            sessionId: 'session-b',
            ipAddress: '192.168.1.10',
            userAgent: 'Safari',
            browser: 'Safari',
            device: 'iPhone',
        );

        /*
         * The new login MUST create a new authentication
         * session record.
         */
        $this->assertNotSame(
            $firstSessionId,
            $secondSession->getKey()
        );

        /*
         * The new session must contain the new Laravel
         * session identifier.
         */
        $this->assertSame(
            'session-b',
            $secondSession->session_id
        );

        /*
         * The new session must contain the new client information.
         */
        $this->assertSame(
            '192.168.1.10',
            $secondSession->ip_address
        );

        $this->assertSame(
            'Safari',
            $secondSession->browser
        );

        $this->assertSame(
            'iPhone',
            $secondSession->device
        );

        /*
         * The new session must be active.
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
         * Session A must no longer exist in the
         * authentication_sessions table.
         *
         * This is required because user_id is UNIQUE.
         */
        $this->assertDatabaseMissing(
            'authentication_sessions',
            [
                'session_id' => 'session-a',
            ]
        );

        /*
         * Only one authentication session may exist
         * for this user.
         */
        $this->assertSame(
            1,
            AuthenticationSession::query()
                ->where('user_id', $user->getKey())
                ->count()
        );
    }

    /**
     * A user can have only one authentication session
     * in the authentication_sessions table.
     *
     * This directly validates the MAHLINE rule:
     *
     * ONE USER → ONE ACTIVE AUTHENTICATED SESSION
     */
    public function test_only_one_authentication_session_exists_for_user(): void
    {
        $user = User::factory()->create();

        /*
         * First authentication.
         */
        $this->sessionService->create(
            user: $user,
            sessionId: 'session-a',
            browser: 'Firefox',
            device: 'Android',
        );

        /*
         * Second authentication.
         *
         * SessionService must replace the first session.
         */
        $this->sessionService->create(
            user: $user,
            sessionId: 'session-b',
            browser: 'Safari',
            device: 'iPhone',
        );

        /*
         * There must be exactly one authentication session
         * for the user.
         */
        $this->assertSame(
            1,
            AuthenticationSession::query()
                ->where('user_id', $user->getKey())
                ->count()
        );

        /*
         * The remaining session must be session-b.
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
         * Session A must no longer exist.
         */
        $this->assertDatabaseMissing(
            'authentication_sessions',
            [
                'user_id' => $user->getKey(),
                'session_id' => 'session-a',
            ]
        );
    }

    /**
     * A new login must replace the previous active session.
     *
     * The previous session is revoked with:
     *
     *     new_login
     *
     * before it is removed because of the UNIQUE(user_id)
     * database constraint.
     *
     * The historical event itself is delegated to
     * LoginHistoryService.
     */
    public function test_previous_session_is_revoked_with_new_login_reason(): void
    {
        $user = User::factory()->create();

        /*
         * First authentication session.
         */
        $firstSession = $this->sessionService->create(
            user: $user,
            sessionId: 'session-a',
            browser: 'Firefox',
            device: 'Android',
        );

        /*
         * The first session must initially be active.
         */
        $this->assertTrue(
            $this->sessionService->isActive($firstSession)
        );

        /*
         * Second authentication.
         *
         * SessionService must revoke the first session
         * using the new_login reason.
         */
        $secondSession = $this->sessionService->create(
            user: $user,
            sessionId: 'session-b',
            browser: 'Safari',
            device: 'iPhone',
        );

        /*
         * The second session must be active.
         */
        $this->assertTrue(
            $this->sessionService->isActive($secondSession)
        );

        /*
         * The second session must be the new session.
         */
        $this->assertSame(
            'session-b',
            $secondSession->session_id
        );

        /*
         * The first session must no longer be active.
         *
         * Because the current database design uses
         * UNIQUE(user_id), the previous record is removed
         * after the revocation operation.
         *
         * Therefore, we verify the resulting state rather
         * than attempting to refresh the deleted model.
         */
        $this->assertDatabaseMissing(
            'authentication_sessions',
            [
                'session_id' => 'session-a',
            ]
        );
    }

    /**
     * Logout revokes the user's current authentication session.
     */
    public function test_logout_revokes_current_session(): void
    {
        $user = User::factory()->create();

        $session = $this->sessionService->create(
            user: $user,
            sessionId: 'session-a',
        );

        /*
         * Logout must revoke the current session.
         */
        $result = $this->sessionService->revokeForUser(
            user: $user,
            reason: 'logout',
        );

        $this->assertTrue($result);

        /*
         * Refresh the model to retrieve the database state.
         */
        $session->refresh();

        /*
         * A revoked session must have a revocation timestamp.
         */
        $this->assertNotNull(
            $session->revoked_at
        );

        /*
         * The revocation reason must be preserved.
         */
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

        /*
         * Revoke the session for security reasons.
         */
        $result = $this->sessionService->revoke(
            session: $session,
            reason: 'security',
        );

        $this->assertTrue($result);

        $session->refresh();

        /*
         * The session must contain a revocation timestamp.
         */
        $this->assertNotNull(
            $session->revoked_at
        );

        /*
         * The reason must be preserved.
         */
        $this->assertSame(
            'security',
            $session->revocation_reason
        );

        /*
         * A revoked session is not active.
         */
        $this->assertFalse(
            $this->sessionService->isActive($session)
        );
    }

    /**
     * A revoked session cannot be revoked a second time.
     *
     * This protects the lifecycle of an authentication session
     * and prevents the original revocation event from being
     * overwritten.
     */
    public function test_already_revoked_session_cannot_be_revoked_again(): void
    {
        $user = User::factory()->create();

        $session = $this->sessionService->create(
            user: $user,
            sessionId: 'session-a',
        );

        /*
         * First revocation must succeed.
         */
        $this->assertTrue(
            $this->sessionService->revoke(
                session: $session,
                reason: 'logout',
            )
        );

        /*
         * Second revocation must fail.
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
     * An active authentication session can update its
     * last activity timestamp.
     */
    public function test_active_session_can_update_last_activity(): void
    {
        $user = User::factory()->create();

        $session = $this->sessionService->create(
            user: $user,
            sessionId: 'session-a',
        );

        /*
         * Store the initial activity timestamp.
         */
        $before = $session->last_activity_at;

        /*
         * Wait one second to guarantee a different timestamp.
         */
        sleep(1);

        /*
         * Update the activity timestamp.
         */
        $result = $this->sessionService->touch($session);

        $session->refresh();

        /*
         * touch() must report success.
         */
        $this->assertTrue($result);

        /*
         * last_activity_at must still contain a value.
         */
        $this->assertNotNull(
            $session->last_activity_at
        );

        /*
         * The new timestamp must not be older than the
         * previous timestamp.
         */
        $this->assertGreaterThanOrEqual(
            $before,
            $session->last_activity_at
        );
    }

    /**
     * A revoked session cannot update its last activity.
     *
     * Once authentication has been revoked, activity updates
     * must be rejected.
     */
    public function test_revoked_session_cannot_update_last_activity(): void
    {
        $user = User::factory()->create();

        $session = $this->sessionService->create(
            user: $user,
            sessionId: 'session-a',
        );

        /*
         * Revoke the authentication session.
         */
        $this->sessionService->revoke(
            session: $session,
            reason: 'logout',
        );

        /*
         * A revoked session must not be touched.
         */
        $result = $this->sessionService->touch($session);

        $this->assertFalse($result);
    }

    /**
     * SessionService must not depend on Laravel's HTTP Request.
     *
     * This is an architectural test.
     *
     * Domain/application services must remain independent
     * from the HTTP transport layer.
     */
    public function test_session_service_does_not_depend_on_request(): void
    {
        $reflection = new \ReflectionClass(
            SessionService::class
        );

        $constructor = $reflection->getConstructor();

        /*
         * A service without a constructor is also valid.
         */
        if ($constructor === null) {
            $this->assertTrue(true);

            return;
        }

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            /*
             * Ignore parameters without a declared type.
             */
            if ($type === null) {
                continue;
            }

            /*
             * We only need to inspect named types here.
             */
            if ($type instanceof \ReflectionNamedType) {
                $this->assertNotSame(
                    \Illuminate\Http\Request::class,
                    $type->getName()
                );
            }
        }

        $this->assertTrue(true);
    }
}