<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Authentication\Services;

use App\Domains\Identity\Authentication\Models\AuthenticationSession;
use App\Domains\Identity\Authentication\Services\SessionService;
use App\Domains\Identity\Enums\UserStatus;
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

        $this->assertInstanceOf(
            AuthenticationSession::class,
            $session,
        );

        $this->assertSame(
            $user->getKey(),
            $session->user_id,
        );

        $this->assertSame(
            'session-a',
            $session->session_id,
        );

        $this->assertSame(
            '127.0.0.1',
            $session->ip_address,
        );

        $this->assertSame(
            'Mozilla/5.0',
            $session->user_agent,
        );

        $this->assertSame(
            'Firefox',
            $session->browser,
        );

        $this->assertSame(
            'Windows',
            $session->device,
        );

        $this->assertNull(
            $session->revoked_at,
        );

        $this->assertNull(
            $session->revocation_reason,
        );

        $this->assertTrue(
            $this->sessionService->isActive($session),
        );

        $this->assertSame(
            1,
            AuthenticationSession::query()
                ->where('user_id', $user->getKey())
                ->count(),
        );
    }

    /**
     * Multiple logins must preserve all active sessions.
     */
    public function test_previous_session_is_retained_without_revocation(): void
    {
        $user = User::factory()->create();

        $previousSession = $this->sessionService->create(
            user: $user,
            sessionId: 'previous-session',
            ipAddress: '192.168.1.10',
            userAgent: 'Mozilla/5.0 Chrome',
            browser: 'Chrome',
            device: 'PC',
        );

        $newSession = $this->sessionService->create(
            user: $user,
            sessionId: 'new-session',
            ipAddress: '192.168.1.20',
            userAgent: 'Mozilla/5.0 Safari',
            browser: 'Safari',
            device: 'Phone',
        );

        $previousSession->refresh();
        $newSession->refresh();

        $this->assertTrue(
            $this->sessionService->isActive($previousSession),
        );

        $this->assertTrue(
            $this->sessionService->isActive($newSession),
        );

        $this->assertNull(
            $previousSession->revoked_at,
        );

        $this->assertNull(
            $newSession->revoked_at,
        );

        $this->assertDatabaseMissing(
            'login_histories',
            [
                'user_id' => $user->getKey(),
                'event' => 'session_revoked',
                'authentication_session_id' => $previousSession->getKey(),
            ],
        );
    }

    /**
     * A new login must not create a session_revoked history entry.
     */
    public function test_new_login_does_not_create_session_revocation_history(): void
    {
        $user = User::factory()->create();

        $firstSession = $this->sessionService->create(
            user: $user,
            sessionId: 'session-a',
        );

        $secondSession = $this->sessionService->create(
            user: $user,
            sessionId: 'session-b',
        );

        $firstSession->refresh();
        $secondSession->refresh();

        $this->assertTrue(
            $this->sessionService->isActive($firstSession),
        );

        $this->assertTrue(
            $this->sessionService->isActive($secondSession),
        );

        $this->assertNull(
            $firstSession->revoked_at,
        );

        $this->assertNull(
            $secondSession->revoked_at,
        );

        $this->assertDatabaseMissing(
            'login_histories',
            [
                'user_id' => $user->getKey(),
                'event' => 'session_revoked',
                'authentication_session_id' => $firstSession->getKey(),
            ],
        );
    }

    /**
     * Logout revokes all active sessions for the user.
     *
     * SessionService::revokeForUser() is intentionally the
     * global-revocation operation.
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
            $session->revoked_at,
        );

        $this->assertSame(
            'logout',
            $session->revocation_reason,
        );

        $this->assertFalse(
            $this->sessionService->isActive($session),
        );

        $this->assertDatabaseHas(
            'login_histories',
            [
                'user_id' => $user->getKey(),
                'event' => 'logout',
                'reason' => 'logout',
                'authentication_session_id' => $session->getKey(),
            ],
        );
    }

    /**
     * A specific authentication session can be revoked independently.
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
            $session->revoked_at,
        );

        $this->assertSame(
            'security',
            $session->revocation_reason,
        );

        $this->assertFalse(
            $this->sessionService->isActive($session),
        );

        $this->assertDatabaseHas(
            'login_histories',
            [
                'user_id' => $user->getKey(),
                'event' => 'session_revoked',
                'reason' => 'security',
                'authentication_session_id' => $session->getKey(),
            ],
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

        $this->assertTrue(
            $this->sessionService->revoke(
                session: $session,
                reason: 'logout',
            ),
        );

        $this->assertFalse(
            $this->sessionService->revoke(
                session: $session,
                reason: 'security',
            ),
        );
    }

    /**
     * An active session can update its last activity.
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
            $session->last_activity_at,
        );

        $this->assertGreaterThanOrEqual(
            $before,
            $session->last_activity_at,
        );
    }

    /**
     * A revoked session cannot update its activity.
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
     * current() resolves a specific active session.
     */
    public function test_current_returns_active_session(): void
    {
        $user = User::factory()->create();

        $session = $this->sessionService->create(
            user: $user,
            sessionId: 'session-a',
        );

        $current = $this->sessionService->current(
            user: $user,
            sessionId: 'session-a',
        );

        $this->assertNotNull($current);

        $this->assertSame(
            $session->getKey(),
            $current->getKey(),
        );

        $this->assertSame(
            'session-a',
            $current->session_id,
        );
    }

    /**
     * current() returns null when the requested session
     * does not exist or is no longer active.
     */
    public function test_current_returns_null_when_no_active_session_exists(): void
    {
        $user = User::factory()->create();

        $this->assertNull(
            $this->sessionService->current(
                user: $user,
                sessionId: 'session-a',
            ),
        );

        $session = $this->sessionService->create(
            user: $user,
            sessionId: 'session-a',
        );

        $this->sessionService->revoke(
            session: $session,
            reason: 'logout',
        );

        $this->assertNull(
            $this->sessionService->current(
                user: $user,
                sessionId: 'session-a',
            ),
        );
    }

    /**
     * SessionService must remain independent from the HTTP Request layer.
     */
    public function test_session_service_does_not_depend_on_request(): void
    {
        $reflection = new ReflectionClass(
            SessionService::class,
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
                Request::class,
                $type instanceof ReflectionNamedType
                    ? $type->getName()
                    : null,
            );
        }

        $this->assertTrue(true);
    }

    /**
     * A phone login does not revoke the computer session.
     */
    public function test_new_login_from_phone_does_not_revoke_computer_session(): void
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

        $this->assertTrue(
            $computerSession->isActive(),
        );

        $this->assertTrue(
            $phoneSession->isActive(),
        );

        $this->assertNull(
            $computerSession->revoked_at,
        );

        $this->assertNull(
            $phoneSession->revoked_at,
        );
    }

    /**
     * Session device information is preserved.
     */
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

        $this->assertSame(
            'Computer',
            $computerSession->device,
        );

        $this->assertSame(
            'Chrome',
            $computerSession->browser,
        );

        $this->assertSame(
            '192.168.1.10',
            $computerSession->ip_address,
        );

        $this->assertSame(
            'Mozilla/5.0 Chrome',
            $computerSession->user_agent,
        );

        $this->assertNull(
            $computerSession->revoked_at,
        );

        $this->assertTrue(
            $computerSession->isActive(),
        );
    }

    /**
     * A user can have multiple active authentication sessions.
     */
    public function test_user_can_have_multiple_active_sessions(): void
    {
        $user = User::factory()->create([
            'status' => UserStatus::Active,
        ]);

        $firstSession = $this->sessionService->create(
            user: $user,
            sessionId: 'session-pc',
            ipAddress: '192.168.1.10',
            userAgent: 'Chrome',
            browser: 'Chrome',
            device: 'PC',
        );

        $secondSession = $this->sessionService->create(
            user: $user,
            sessionId: 'session-phone',
            ipAddress: '192.168.1.20',
            userAgent: 'Safari',
            browser: 'Safari',
            device: 'Phone',
        );

        $this->assertTrue(
            $this->sessionService->isActive($firstSession),
        );

        $this->assertTrue(
            $this->sessionService->isActive($secondSession),
        );

        $this->assertDatabaseHas(
            'authentication_sessions',
            [
                'id' => $firstSession->getKey(),
                'revoked_at' => null,
            ],
        );

        $this->assertDatabaseHas(
            'authentication_sessions',
            [
                'id' => $secondSession->getKey(),
                'revoked_at' => null,
            ],
        );
    }

    /**
     * Revoking one session must not revoke another session.
     */
    public function test_revoking_one_session_does_not_revoke_other_sessions(): void
    {
        $user = User::factory()->create([
            'status' => UserStatus::Active,
        ]);

        $firstSession = $this->sessionService->create(
            user: $user,
            sessionId: 'session-pc',
            device: 'PC',
        );

        $secondSession = $this->sessionService->create(
            user: $user,
            sessionId: 'session-phone',
            device: 'Phone',
        );

        $result = $this->sessionService->revoke(
            session: $firstSession,
            reason: 'logout',
        );

        $this->assertTrue($result);

        $this->assertFalse(
            $this->sessionService->isActive(
                $firstSession->fresh(),
            ),
        );

        $this->assertTrue(
            $this->sessionService->isActive(
                $secondSession->fresh(),
            ),
        );
    }

    /**
     * Multiple active authentication sessions can coexist.
     */
    public function test_multiple_active_authentication_sessions_can_exist_for_user(): void
    {
        $user = User::factory()->create();

        $firstSession = $this->sessionService->create(
            user: $user,
            sessionId: 'session-pc',
            device: 'PC',
        );

        $secondSession = $this->sessionService->create(
            user: $user,
            sessionId: 'session-phone',
            device: 'Phone',
        );

        $this->assertTrue(
            $firstSession->fresh()->isActive(),
        );

        $this->assertTrue(
            $secondSession->fresh()->isActive(),
        );

        $activeSessions = AuthenticationSession::query()
            ->where('user_id', $user->getKey())
            ->whereNull('revoked_at')
            ->count();

        $this->assertSame(
            2,
            $activeSessions,
        );
    }

    /**
     * Global revocation revokes all active sessions of a user.
     */
    public function test_revoke_for_user_revokes_all_active_sessions(): void
    {
        $user = User::factory()->create();

        $firstSession = $this->sessionService->create(
            user: $user,
            sessionId: 'global-logout-session-1',
            ipAddress: '127.0.0.1',
            userAgent: 'PHPUnit',
            browser: 'Browser 1',
            device: 'Device 1',
        );

        $secondSession = $this->sessionService->create(
            user: $user,
            sessionId: 'global-logout-session-2',
            ipAddress: '127.0.0.2',
            userAgent: 'PHPUnit',
            browser: 'Browser 2',
            device: 'Device 2',
        );

        $result = $this->sessionService->revokeForUser(
            user: $user,
            reason: 'global_logout',
        );

        $this->assertTrue($result);

        $firstSession->refresh();
        $secondSession->refresh();

        $this->assertNotNull($firstSession->revoked_at);
        $this->assertNotNull($secondSession->revoked_at);

        $this->assertSame(
            'global_logout',
            $firstSession->revocation_reason,
        );

        $this->assertSame(
            'global_logout',
            $secondSession->revocation_reason,
        );
    }

    /**
     * A revoked session is not counted as active.
     */
    public function test_revoked_session_is_not_counted_as_active(): void
    {
        $user = User::factory()->create([
            'status' => UserStatus::Active,
        ]);

        $firstSession = $this->sessionService->create(
            user: $user,
            sessionId: 'session-active-check-001',
        );

        $this->sessionService->create(
            user: $user,
            sessionId: 'session-active-check-002',
        );

        $this->sessionService->revoke(
            session: $firstSession,
            reason: 'logout',
        );

        $activeSessions = AuthenticationSession::query()
            ->where('user_id', $user->getKey())
            ->whereNull('revoked_at')
            ->count();

        $this->assertSame(
            1,
            $activeSessions,
        );
    }

    /**
     * Revoking one session keeps all other sessions active.
     */
    public function test_revoking_one_session_keeps_other_sessions_active(): void
    {
        $user = User::factory()->create();

        $firstSession = $this->sessionService->create(
            user: $user,
            sessionId: 'session-revoke-one-001',
        );

        $secondSession = $this->sessionService->create(
            user: $user,
            sessionId: 'session-revoke-one-002',
        );

        $this->sessionService->revoke(
            session: $firstSession,
            reason: 'logout',
        );

        $firstSession->refresh();
        $secondSession->refresh();

        $this->assertNotNull(
            $firstSession->revoked_at,
        );

        $this->assertNull(
            $secondSession->revoked_at,
        );

        $this->assertSame(
            1,
            AuthenticationSession::query()
                ->where('user_id', $user->getKey())
                ->whereNull('revoked_at')
                ->count(),
        );
    }

    /**
     * Revoking an already revoked session returns false.
     */
    public function test_revoking_an_already_revoked_session_returns_false(): void
    {
        $user = User::factory()->create();

        $session = $this->sessionService->create(
            user: $user,
            sessionId: 'session-already-revoked-001',
        );

        $firstRevocation = $this->sessionService->revoke(
            session: $session,
            reason: 'logout',
        );

        $session->refresh();

        $secondRevocation = $this->sessionService->revoke(
            session: $session,
            reason: 'logout',
        );

        $this->assertTrue($firstRevocation);
        $this->assertFalse($secondRevocation);
        $this->assertNotNull($session->revoked_at);
    }

    /**
     * A deleted session cannot be revoked.
     */
    public function test_deleted_session_cannot_be_revoked(): void
    {
        $user = User::factory()->create();

        $session = $this->sessionService->create(
            user: $user,
            sessionId: 'session-deleted-001',
        );

        $session->delete();
        $session->refresh();

        $result = $this->sessionService->revoke(
            session: $session,
            reason: 'logout',
        );

        $this->assertFalse($result);

        $this->assertNotNull(
            $session->deleted_at,
        );
    }

    /**
     * A revoked session keeps its revocation reason.
     */
    public function test_revoked_session_keeps_its_revocation_reason(): void
    {
        $user = User::factory()->create();

        $session = $this->sessionService->create(
            user: $user,
            sessionId: 'session-reason-001',
        );

        $reason = 'security_review';

        $result = $this->sessionService->revoke(
            session: $session,
            reason: $reason,
        );

        $session->refresh();

        $this->assertTrue($result);

        $this->assertNotNull(
            $session->revoked_at,
        );

        $this->assertSame(
            $reason,
            $session->revocation_reason,
        );

        $this->assertDatabaseHas(
            'authentication_sessions',
            [
                'session_id' => 'session-reason-001',
                'revocation_reason' => $reason,
            ],
        );
    }

    /**
     * A custom session revocation records a session_revoked event.
     */
    public function test_custom_session_revocation_records_session_revoked_history(): void
    {
        $user = User::factory()->create();

        $session = $this->sessionService->create(
            user: $user,
            sessionId: 'session-history-001',
            ipAddress: '127.0.0.217',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $this->sessionService->revoke(
            session: $session,
            reason: 'security_review',
        );

        $this->assertDatabaseHas(
            'login_histories',
            [
                'user_id' => $user->getKey(),
                'event' => 'session_revoked',
                'reason' => 'security_review',
                'authentication_session_id' => $session->getKey(),
                'ip_address' => '127.0.0.217',
                'browser' => 'Firefox',
                'device' => 'Desktop',
            ],
        );
    }

    /**
     * Custom revocation must not create a logout event.
     */
    public function test_custom_session_revocation_does_not_record_logout_history_event(): void
    {
        $user = User::factory()->create();

        $session = $this->sessionService->create(
            user: $user,
            sessionId: 'session-no-logout-001',
        );

        $this->sessionService->revoke(
            session: $session,
            reason: 'security_review',
        );

        $this->assertDatabaseMissing(
            'login_histories',
            [
                'user_id' => $user->getKey(),
                'event' => 'logout',
                'authentication_session_id' => $session->getKey(),
            ],
        );
    }

    /**
     * revokeForUser() returns false when the user has no active sessions.
     */
    public function test_revoke_for_user_returns_false_when_no_active_session_exists(): void
    {
        $user = User::factory()->create();

        $result = $this->sessionService->revokeForUser(
            user: $user,
            reason: 'global_logout',
        );

        $this->assertFalse($result);

        $this->assertSame(
            0,
            AuthenticationSession::query()
                ->where('user_id', $user->getKey())
                ->count(),
        );
    }

    /**
     * revokeForUser() ignores sessions already revoked.
     */
    public function test_revoke_for_user_ignores_already_revoked_sessions(): void
    {
        $user = User::factory()->create();

        $firstSession = $this->sessionService->create(
            user: $user,
            sessionId: 'session-revoke-existing-001',
        );

        $secondSession = $this->sessionService->create(
            user: $user,
            sessionId: 'session-revoke-existing-002',
        );

        $this->sessionService->revoke(
            session: $firstSession,
            reason: 'logout',
        );

        $revokedAt = $firstSession->fresh()->revoked_at;

        $result = $this->sessionService->revokeForUser(
            user: $user,
            reason: 'global_logout',
        );

        $this->assertTrue($result);

        $firstSession->refresh();
        $secondSession->refresh();

        $this->assertTrue(
            $firstSession->revoked_at->equalTo($revokedAt),
        );

        $this->assertSame(
            'logout',
            $firstSession->revocation_reason,
        );

        $this->assertNotNull(
            $secondSession->revoked_at,
        );

        $this->assertSame(
            'global_logout',
            $secondSession->revocation_reason,
        );
    }

    /**
     * revokeForUser() ignores deleted sessions.
     */
    public function test_revoke_for_user_ignores_deleted_sessions(): void
    {
        $user = User::factory()->create();

        $session = $this->sessionService->create(
            user: $user,
            sessionId: 'session-revoke-deleted-001',
        );

        $session->delete();

        $result = $this->sessionService->revokeForUser(
            user: $user,
            reason: 'global_logout',
        );

        $this->assertFalse($result);

        $this->assertNotNull(
            $session->fresh()->deleted_at,
        );
    }

    /**
     * revokeForUser() must never revoke another user's sessions.
     */
    public function test_revoke_for_user_does_not_revoke_another_users_sessions(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $ownerSession = $this->sessionService->create(
            user: $user,
            sessionId: 'session-owner-001',
        );

        $otherSession = $this->sessionService->create(
            user: $otherUser,
            sessionId: 'session-other-001',
        );

        $result = $this->sessionService->revokeForUser(
            user: $user,
            reason: 'global_logout',
        );

        $this->assertTrue($result);

        $ownerSession->refresh();
        $otherSession->refresh();

        $this->assertNotNull(
            $ownerSession->revoked_at,
        );

        $this->assertNull(
            $otherSession->revoked_at,
        );
    }

    /**
     * revokeForUser() preserves existing revocation reasons.
     */
    public function test_revoke_for_user_does_not_change_existing_revocation_reason(): void
    {
        $user = User::factory()->create();

        $alreadyRevoked = $this->sessionService->create(
            user: $user,
            sessionId: 'session-reason-preserved-001',
        );

        $activeSession = $this->sessionService->create(
            user: $user,
            sessionId: 'session-reason-preserved-002',
        );

        $this->sessionService->revoke(
            session: $alreadyRevoked,
            reason: 'security_review',
        );

        $this->sessionService->revokeForUser(
            user: $user,
            reason: 'global_logout',
        );

        $alreadyRevoked->refresh();
        $activeSession->refresh();

        $this->assertSame(
            'security_review',
            $alreadyRevoked->revocation_reason,
        );

        $this->assertNotNull(
            $alreadyRevoked->revoked_at,
        );

        $this->assertSame(
            'global_logout',
            $activeSession->revocation_reason,
        );
    }

    /**
     * revokeForUser() returns true when an active session is revoked.
     */
    public function test_revoke_for_user_returns_true_when_an_active_session_is_revoked(): void
    {
        $user = User::factory()->create();

        $this->sessionService->create(
            user: $user,
            sessionId: 'session-revoke-return-true-001',
        );

        $result = $this->sessionService->revokeForUser(
            user: $user,
            reason: 'global_logout',
        );

        $this->assertTrue($result);
    }

    /**
     * revokeForUser() revokes every active session.
     */
    public function test_revoke_for_user_revokes_all_active_sessions_and_returns_true(): void
    {
        $user = User::factory()->create();

        $firstSession = $this->sessionService->create(
            user: $user,
            sessionId: 'session-revoke-all-001',
        );

        $secondSession = $this->sessionService->create(
            user: $user,
            sessionId: 'session-revoke-all-002',
        );

        $result = $this->sessionService->revokeForUser(
            user: $user,
            reason: 'global_logout',
        );

        $this->assertTrue($result);

        $firstSession->refresh();
        $secondSession->refresh();

        $this->assertSame(
            'global_logout',
            $firstSession->revocation_reason,
        );

        $this->assertSame(
            'global_logout',
            $secondSession->revocation_reason,
        );
    }

    /**
     * Global logout must create session_revoked history entries,
     * not individual logout events.
     */
    public function test_revoke_for_user_does_not_record_individual_logout_history_events(): void
    {
        $user = User::factory()->create();

        $firstSession = $this->sessionService->create(
            user: $user,
            sessionId: 'session-global-history-001',
        );

        $secondSession = $this->sessionService->create(
            user: $user,
            sessionId: 'session-global-history-002',
        );

        $result = $this->sessionService->revokeForUser(
            user: $user,
            reason: 'global_logout',
        );

        $this->assertTrue($result);

        $this->assertDatabaseMissing(
            'login_histories',
            [
                'user_id' => $user->getKey(),
                'event' => 'logout',
                'authentication_session_id' => $firstSession->getKey(),
            ],
        );

        $this->assertDatabaseMissing(
            'login_histories',
            [
                'user_id' => $user->getKey(),
                'event' => 'logout',
                'authentication_session_id' => $secondSession->getKey(),
            ],
        );

        $this->assertDatabaseHas(
            'login_histories',
            [
                'user_id' => $user->getKey(),
                'event' => 'session_revoked',
                'authentication_session_id' => $firstSession->getKey(),
                'reason' => 'global_logout',
            ],
        );

        $this->assertDatabaseHas(
            'login_histories',
            [
                'user_id' => $user->getKey(),
                'event' => 'session_revoked',
                'authentication_session_id' => $secondSession->getKey(),
                'reason' => 'global_logout',
            ],
        );
    }

    /**
     * Global logout preserves its reason on every revoked session.
     */
    public function test_revoke_for_user_preserves_global_logout_reason_on_all_sessions(): void
    {
        $user = User::factory()->create();

        $firstSession = $this->sessionService->create(
            user: $user,
            sessionId: 'session-global-reason-001',
        );

        $secondSession = $this->sessionService->create(
            user: $user,
            sessionId: 'session-global-reason-002',
        );

        $result = $this->sessionService->revokeForUser(
            user: $user,
            reason: 'global_logout',
        );

        $this->assertTrue($result);

        $firstSession->refresh();
        $secondSession->refresh();

        $this->assertSame(
            'global_logout',
            $firstSession->revocation_reason,
        );

        $this->assertSame(
            'global_logout',
            $secondSession->revocation_reason,
        );
    }

    /**
     * revokeForUser() returns false when every session is already revoked.
     */
    public function test_revoke_for_user_returns_false_when_all_sessions_are_already_revoked(): void
    {
        $user = User::factory()->create();

        $session = $this->sessionService->create(
            user: $user,
            sessionId: 'session-all-revoked-001',
        );

        $session->revoked_at = now();
        $session->revocation_reason = 'logout';
        $session->save();

        $result = $this->sessionService->revokeForUser(
            user: $user,
            reason: 'global_logout',
        );

        $this->assertFalse($result);

        $this->assertDatabaseHas(
            'authentication_sessions',
            [
                'session_id' => 'session-all-revoked-001',
                'revocation_reason' => 'logout',
            ],
        );
    }

    /**
     * revokeForUser() only affects the requested user.
     */
    public function test_revoke_for_user_does_not_revoke_sessions_of_another_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $ownerSession = $this->sessionService->create(
            user: $user,
            sessionId: 'session-owner-001',
        );

        $otherSession = $this->sessionService->create(
            user: $otherUser,
            sessionId: 'session-other-001',
        );

        $result = $this->sessionService->revokeForUser(
            user: $user,
            reason: 'global_logout',
        );

        $this->assertTrue($result);

        $ownerSession->refresh();
        $otherSession->refresh();

        $this->assertSame(
            'global_logout',
            $ownerSession->revocation_reason,
        );

        $this->assertNull(
            $otherSession->revoked_at,
        );

        $this->assertNull(
            $otherSession->revocation_reason,
        );
    }

    /**
     * revokeForUser() only revokes active sessions.
     */
    public function test_revoke_for_user_revokes_only_active_sessions(): void
    {
        $user = User::factory()->create();

        $activeSession = $this->sessionService->create(
            user: $user,
            sessionId: 'session-mixed-active-001',
        );

        $revokedSession = $this->sessionService->create(
            user: $user,
            sessionId: 'session-mixed-revoked-001',
        );

        $this->sessionService->revoke(
            session: $revokedSession,
            reason: 'logout',
        );

        $result = $this->sessionService->revokeForUser(
            user: $user,
            reason: 'global_logout',
        );

        $this->assertTrue($result);

        $activeSession->refresh();
        $revokedSession->refresh();

        $this->assertSame(
            'global_logout',
            $activeSession->revocation_reason,
        );

        $this->assertSame(
            'logout',
            $revokedSession->revocation_reason,
        );
    }
}
