<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Authentication\Services;

use App\Domains\Identity\Authentication\Models\AuthenticationSession;
use App\Domains\Identity\Authentication\Models\LoginHistory;
use App\Domains\Identity\Authentication\Services\LoginHistoryService;
use App\Domains\Identity\Authentication\Services\SessionService;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use ReflectionClass;
use ReflectionNamedType;
use Tests\TestCase;

final class SessionServiceTest extends TestCase
{
    use RefreshDatabase;

    private SessionService $sessionService;

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * Resolve the service through Laravel's container.
         *
         * SessionService depends on LoginHistoryService.
         */
        $this->sessionService = app(SessionService::class);
    }

    /**
     * Test that a new authentication session can be created.
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
            device: 'Windows',
        );

        /*
         * The returned object must be an AuthenticationSession.
         */
        $this->assertInstanceOf(
            AuthenticationSession::class,
            $session
        );

        /*
         * The session belongs to the expected user.
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
         * Client information must be stored.
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
            'Windows',
            $session->device
        );

        /*
         * A newly created authentication session is active.
         */
        $this->assertNull(
            $session->revoked_at
        );

        $this->assertNull(
            $session->revocation_reason
        );

        $this->assertTrue(
            $this->sessionService->isActive($session)
        );

        /*
         * Exactly one authentication session must exist.
         */
        $this->assertSame(
            1,
            AuthenticationSession::query()
                ->where('user_id', $user->getKey())
                ->count()
        );
    }

    /**
     * Test that a new login replaces the current session.
     *
     * IMPORTANT:
     *
     * The previous authentication session is NOT kept as a second
     * row in authentication_sessions.
     *
     * The same database row is reused.
     *
     * The historical information about the previous login is stored
     * in login_histories.
     */
    // public function test_only_one_active_authentication_session_exists_for_user(): void
    // {
    //     $user = User::factory()->create();

    //     /*
    //     * First login.
    //     */
    //     $firstSession = $this->sessionService->create(
    //         user: $user,
    //         sessionId: 'session-a',
    //         ipAddress: '127.0.0.1',
    //         userAgent: 'Mozilla/5.0',
    //         browser: 'Firefox',
    //         device: 'Windows',
    //     );

    //     /*
    //     * Second login.
    //     *
    //     * The first session must be revoked and preserved.
    //     */
    //     $secondSession = $this->sessionService->create(
    //         user: $user,
    //         sessionId: 'session-b',
    //         ipAddress: '192.168.1.10',
    //         userAgent: 'Mozilla/5.0 Chrome',
    //         browser: 'Chrome',
    //         device: 'Android',
    //     );

    //     /*
    //     * Third login.
    //     *
    //     * The second session must now be revoked and preserved.
    //     */
    //     $thirdSession = $this->sessionService->create(
    //         user: $user,
    //         sessionId: 'session-c',
    //         ipAddress: '192.168.1.20',
    //         userAgent: 'Mozilla/5.0 Safari',
    //         browser: 'Safari',
    //         device: 'iPhone',
    //     );

    //     /*
    //     * There are now THREE historical authentication
    //     * session records.
    //     *
    //     * Session A → revoked
    //     * Session B → revoked
    //     * Session C → active
    //     */
    //     $this->assertSame(
    //         3,
    //         AuthenticationSession::query()
    //             ->where('user_id', $user->getKey())
    //             ->count(),
    //     );

    //     /*
    //     * Only ONE authentication session may be active.
    //     */
    //     $this->assertSame(
    //         1,
    //         AuthenticationSession::query()
    //             ->where('user_id', $user->getKey())
    //             ->whereNull('revoked_at')
    //             ->count(),
    //     );

    //     /*
    //     * The third session must be the active session.
    //     */
    //     $this->assertSame(
    //         $thirdSession->getKey(),
    //         AuthenticationSession::query()
    //             ->where('user_id', $user->getKey())
    //             ->whereNull('revoked_at')
    //             ->value('id'),
    //     );

    //     /*
    //     * The first session must be revoked.
    //     */
    //     $firstSession->refresh();

    //     $this->assertNotNull(
    //         $firstSession->revoked_at,
    //     );

    //     $this->assertSame(
    //         'new_login',
    //         $firstSession->revocation_reason,
    //     );

    //     /*
    //     * The second session must also be revoked.
    //     */
    //     $secondSession->refresh();

    //     $this->assertNotNull(
    //         $secondSession->revoked_at,
    //     );

    //     $this->assertSame(
    //         'new_login',
    //         $secondSession->revocation_reason,
    //     );

    //     /*
    //     * The third session must remain active.
    //     */
    //     $thirdSession->refresh();

    //     $this->assertNull(
    //         $thirdSession->revoked_at,
    //     );

    //     $this->assertNull(
    //         $thirdSession->revocation_reason,
    //     );
    // }

    /**
     * Test that the previous login is preserved in LoginHistory.
     *
     * The old authentication session is represented historically
     * by a login_histories record.
     */
    public function test_previous_session_is_retained_in_login_history(): void
    {
        $user = User::factory()->create();

        /*
         * First login.
         */
        $firstSession = $this->sessionService->create(
            user: $user,
            sessionId: 'session-a',
            ipAddress: '127.0.0.1',
            userAgent: 'Mozilla/5.0 Firefox',
            browser: 'Firefox',
            device: 'Windows',
        );

        /*
         * Second login.
         *
         * This replaces the current session.
         */
        $secondSession = $this->sessionService->create(
            user: $user,
            sessionId: 'session-b',
            ipAddress: '192.168.1.10',
            userAgent: 'Mozilla/5.0 Chrome',
            browser: 'Chrome',
            device: 'Android',
        );

        /*
         * The current authentication session is the new one.
         */
        $this->assertSame(
            'session-b',
            $secondSession->session_id
        );

        /*
         * The historical revocation must be stored in LoginHistory.
         */
        $this->assertDatabaseHas(
            'login_histories',
            [
                'user_id' => $user->getKey(),
                'event' => 'session_revoked',
                'reason' => 'new_login',
                'authentication_session_id' => $firstSession->getKey(),
            ]
        );
    }

    /**
     * Test that only one authentication session exists for a user.
     *
     * Multiple logins do not create multiple authentication_sessions
     * rows.
     * here new function
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
            ipAddress: '127.0.0.1',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Windows',
        );

        /*
        * Second login.
        *
        * The first session must be revoked.
        */
        $secondSession = $this->sessionService->create(
            user: $user,
            sessionId: 'session-b',
            ipAddress: '192.168.1.10',
            userAgent: 'Mozilla/5.0 Chrome',
            browser: 'Chrome',
            device: 'Android',
        );

        /*
        * Third login.
        *
        * The second session must be revoked.
        */
        $thirdSession = $this->sessionService->create(
            user: $user,
            sessionId: 'session-c',
            ipAddress: '192.168.1.20',
            userAgent: 'Mozilla/5.0 Safari',
            browser: 'Safari',
            device: 'iPhone',
        );

        /*
        * Three historical authentication sessions exist.
        */
        $this->assertSame(
            3,
            AuthenticationSession::query()
                ->where('user_id', $user->getKey())
                ->count(),
        );

        /*
        * But only ONE authentication session is active.
        */
        $this->assertSame(
            1,
            AuthenticationSession::query()
                ->where('user_id', $user->getKey())
                ->whereNull('revoked_at')
                ->count(),
        );

        /*
        * The first session is revoked.
        */
        $firstSession->refresh();

        $this->assertNotNull(
            $firstSession->revoked_at,
        );

        $this->assertSame(
            'new_login',
            $firstSession->revocation_reason,
        );

        /*
        * The second session is revoked.
        */
        $secondSession->refresh();

        $this->assertNotNull(
            $secondSession->revoked_at,
        );

        $this->assertSame(
            'new_login',
            $secondSession->revocation_reason,
        );

        /*
        * The third session is active.
        */
        $thirdSession->refresh();

        $this->assertNull(
            $thirdSession->revoked_at,
        );

        $this->assertNull(
            $thirdSession->revocation_reason,
        );

        /*
        * The current() method must return the third session.
        */
        $currentSession = $this->sessionService->current($user);

        $this->assertNotNull($currentSession);

        $this->assertSame(
            $thirdSession->getKey(),
            $currentSession->getKey(),
        );
    }

    /**
     * Test that a previous session replacement is recorded
     * with the "new_login" reason.
     */
    public function test_previous_session_revocation_is_recorded_in_login_history(): void
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
         * Second login replaces the first one.
         */
        $secondSession = $this->sessionService->create(
            user: $user,
            sessionId: 'session-b',
        );

        /*
         * The current session must be active.
         */
        $this->assertTrue(
            $this->sessionService->isActive($secondSession)
        );

        /*
         * The historical replacement event must exist.
         */
        $this->assertDatabaseHas(
            'login_histories',
            [
                'user_id' => $user->getKey(),
                'event' => 'session_revoked',
                'reason' => 'new_login',
                'authentication_session_id' => $firstSession->getKey(),
            ]
        );
    }

    /**
     * Test that logout revokes the current authentication session.
     */
    public function test_logout_revokes_current_session(): void
    {
        $user = User::factory()->create();

        /*
         * Create an active session.
         */
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
         * Refresh the model from the database.
         */
        $session->refresh();

        /*
         * The session must now be revoked.
         */
        $this->assertNotNull(
            $session->revoked_at
        );

        $this->assertSame(
            'logout',
            $session->revocation_reason
        );

        /*
         * A revoked session is no longer active.
         */
        $this->assertFalse(
            $this->sessionService->isActive($session)
        );

        /*
         * Logout must be recorded as a logout event.
         */
        $this->assertDatabaseHas(
            'login_histories',
            [
                'user_id' => $user->getKey(),
                'event' => 'logout',
                'reason' => 'logout',
                'authentication_session_id' => $session->getKey(),
            ]
        );
    }

    /**
     * Test that a specific authentication session can be revoked.
     */
    public function test_revoke_specific_session(): void
    {
        $user = User::factory()->create();

        $session = $this->sessionService->create(
            user: $user,
            sessionId: 'session-a',
        );

        /*
         * Revoke the session directly.
         */
        $result = $this->sessionService->revoke(
            session: $session,
            reason: 'security',
        );

        $this->assertTrue($result);

        /*
         * Refresh from database.
         */
        $session->refresh();

        /*
         * The session must be revoked.
         */
        $this->assertNotNull(
            $session->revoked_at
        );

        $this->assertSame(
            'security',
            $session->revocation_reason
        );

        /*
         * The session must no longer be active.
         */
        $this->assertFalse(
            $this->sessionService->isActive($session)
        );

        /*
         * Security revocation must be recorded in LoginHistory.
         */
        $this->assertDatabaseHas(
            'login_histories',
            [
                'user_id' => $user->getKey(),
                'event' => 'session_revoked',
                'reason' => 'security',
                'authentication_session_id' => $session->getKey(),
            ]
        );
    }

    /**
     * Test that an already revoked session cannot be revoked again.
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
         * A second revocation must be rejected.
         */
        $this->assertFalse(
            $this->sessionService->revoke(
                session: $session,
                reason: 'security',
            )
        );
    }

    /**
     * Test that an active session can update its last activity.
     */
    public function test_active_session_can_update_last_activity(): void
    {
        $user = User::factory()->create();

        $session = $this->sessionService->create(
            user: $user,
            sessionId: 'session-a',
        );

        /*
         * Store the previous activity timestamp.
         */
        $before = $session->last_activity_at;

        /*
         * Wait briefly so the timestamp can change.
         */
        sleep(1);

        /*
         * Update the activity timestamp.
         */
        $result = $this->sessionService->touch($session);

        $session->refresh();

        $this->assertTrue($result);

        $this->assertNotNull(
            $session->last_activity_at
        );

        /*
         * The new activity timestamp must not be older
         * than the previous one.
         */
        $this->assertGreaterThanOrEqual(
            $before,
            $session->last_activity_at
        );
    }

    /**
     * Test that a revoked session cannot update last activity.
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
         * A revoked session must not be updated.
         */
        $result = $this->sessionService->touch($session);

        $this->assertFalse($result);
    }

    /**
     * Test that current() returns the active authentication session.
     */
    public function test_current_returns_active_session(): void
    {
        $user = User::factory()->create();

        $session = $this->sessionService->create(
            user: $user,
            sessionId: 'session-a',
        );

        /*
         * current() must return the active session.
         */
        $current = $this->sessionService->current(
            user: $user,
        );

        $this->assertNotNull($current);

        $this->assertSame(
            $session->getKey(),
            $current->getKey()
        );

        $this->assertSame(
            'session-a',
            $current->session_id
        );
    }

    /**
     * Test that current() returns null when there is
     * no active authentication session.
     */
    public function test_current_returns_null_when_no_active_session_exists(): void
    {
        $user = User::factory()->create();

        /*
         * No authentication session exists yet.
         */
        $this->assertNull(
            $this->sessionService->current(
                user: $user,
            )
        );

        /*
         * Create a session and revoke it.
         */
        $session = $this->sessionService->create(
            user: $user,
            sessionId: 'session-a',
        );

        $this->sessionService->revoke(
            session: $session,
            reason: 'logout',
        );

        /*
         * There is now no active session.
         */
        $this->assertNull(
            $this->sessionService->current(
                user: $user,
            )
        );
    }

    /**
     * Test that SessionService does not depend on Laravel Request.
     *
     * Domain services must remain independent from the HTTP layer.
     */
    public function test_session_service_does_not_depend_on_request(): void
    {
        $reflection = new ReflectionClass(
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

        /*
         * Inspect every constructor dependency.
         */
        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type === null) {
                continue;
            }

            /*
             * The constructor must never receive
             * Illuminate\Http\Request.
             */
            $this->assertNotSame(
                Request::class,
                $type instanceof ReflectionNamedType
                    ? $type->getName()
                    : null
            );
        }

        $this->assertTrue(true);
    }
    public function test_new_login_from_phone_revokes_previous_computer_session(): void
    {
        $user = User::factory()->create();

        /*
        * First login: computer.
        */
        $computerSession = $this->sessionService->create(
            user: $user,
            sessionId: 'computer-session-001',
            ipAddress: '192.168.1.10',
            userAgent: 'Mozilla/5.0 Chrome',
            browser: 'Chrome',
            device: 'Computer',
        );

        /*
        * Second login: phone.
        */
        $phoneSession = $this->sessionService->create(
            user: $user,
            sessionId: 'phone-session-001',
            ipAddress: '192.168.1.20',
            userAgent: 'Mozilla/5.0 Mobile Safari',
            browser: 'Safari',
            device: 'Phone',
        );

        $computerSession->refresh();
        $phoneSession->refresh();

        $this->assertFalse($computerSession->isActive());
        $this->assertTrue($phoneSession->isActive());

        $this->assertNotNull($computerSession->revoked_at);

        $this->assertSame(
            'new_login',
            $computerSession->revocation_reason,
        );
    }

    public function test_user_can_never_have_two_active_sessions_on_different_devices(): void
    {
        $user = User::factory()->create();

        $this->sessionService->create(
            user: $user,
            sessionId: 'computer-session-001',
            ipAddress: '192.168.1.10',
            userAgent: 'Mozilla/5.0 Chrome',
            browser: 'Chrome',
            device: 'Computer',
        );

        $this->sessionService->create(
            user: $user,
            sessionId: 'phone-session-001',
            ipAddress: '192.168.1.20',
            userAgent: 'Mozilla/5.0 Mobile Safari',
            browser: 'Safari',
            device: 'Phone',
        );

        $activeSessions = AuthenticationSession::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->count();

        $this->assertSame(1, $activeSessions);
    }

    public function test_previous_session_keeps_device_information_after_revocation(): void
    {
        $user = User::factory()->create();

        $computerSession = $this->sessionService->create(
            user: $user,
            sessionId: 'computer-session-001',
            ipAddress: '192.168.1.10',
            userAgent: 'Mozilla/5.0 Chrome',
            browser: 'Chrome',
            device: 'Computer',
        );

        $this->sessionService->create(
            user: $user,
            sessionId: 'phone-session-001',
            ipAddress: '192.168.1.20',
            userAgent: 'Mozilla/5.0 Mobile Safari',
            browser: 'Safari',
            device: 'Phone',
        );

        $computerSession->refresh();

        $this->assertSame('Computer', $computerSession->device);
        $this->assertSame('Chrome', $computerSession->browser);
        $this->assertSame('192.168.1.10', $computerSession->ip_address);
        $this->assertSame('Mozilla/5.0 Chrome', $computerSession->user_agent);

        $this->assertNotNull($computerSession->revoked_at);
    }
}