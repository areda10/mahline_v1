<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Authentication\Models;

use App\Domains\Identity\Authentication\Models\AuthenticationSession;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AuthenticationSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_authentication_session_can_be_created(): void
    {
        $user = User::factory()->create();

        $session = AuthenticationSession::query()->create([
            'user_id' => $user->id,
            'session_id' => 'test-session-id',
            'authenticated_at' => now(),
        ]);

        $this->assertDatabaseHas('authentication_sessions', [
            'id' => $session->id,
            'user_id' => $user->id,
            'session_id' => 'test-session-id',
        ]);

        $this->assertNotEmpty($session->id);
        $this->assertSame(26, strlen($session->id));
    }

    public function test_authentication_session_uses_ulid_primary_key(): void
    {
        $session = AuthenticationSession::query()->make();

        $this->assertSame('string', $session->getKeyType());
        $this->assertFalse($session->getIncrementing());
    }

    public function test_authentication_session_belongs_to_user(): void
    {
        $user = User::factory()->create();

        $session = AuthenticationSession::query()->create([
            'user_id' => $user->id,
            'session_id' => 'test-session-id',
            'authenticated_at' => now(),
        ]);

        $this->assertInstanceOf(
            User::class,
            $session->user
        );

        $this->assertSame(
            $user->id,
            $session->user->id
        );
    }

    public function test_new_authentication_session_is_active(): void
    {
        $user = User::factory()->create();

        $session = AuthenticationSession::query()->create([
            'user_id' => $user->id,
            'session_id' => 'test-session-id',
            'authenticated_at' => now(),
        ]);

        $this->assertTrue($session->isActive());
        $this->assertNull($session->revoked_at);
    }

    public function test_authentication_session_can_be_revoked(): void
    {
        $user = User::factory()->create();

        $session = AuthenticationSession::query()->create([
            'user_id' => $user->id,
            'session_id' => 'test-session-id',
            'authenticated_at' => now(),
        ]);

        $session->revoke('new_login');

        $session->refresh();

        $this->assertFalse($session->isActive());
        $this->assertNotNull($session->revoked_at);
        $this->assertSame(
            'new_login',
            $session->revocation_reason
        );
    }

    public function test_authentication_session_datetime_attributes_are_cast(): void
    {
        $user = User::factory()->create();

        $session = AuthenticationSession::query()->create([
            'user_id' => $user->id,
            'session_id' => 'test-session-id',
            'authenticated_at' => now(),
            'last_activity_at' => now(),
            'revoked_at' => null,
        ]);

        $this->assertInstanceOf(
            \Illuminate\Support\Carbon::class,
            $session->authenticated_at
        );

        $this->assertInstanceOf(
            \Illuminate\Support\Carbon::class,
            $session->last_activity_at
        );

        $this->assertNull($session->revoked_at);
    }

    public function test_session_id_is_hidden_from_array_representation(): void
    {
        $user = User::factory()->create();

        $session = AuthenticationSession::query()->create([
            'user_id' => $user->id,
            'session_id' => 'secret-session-id',
            'authenticated_at' => now(),
        ]);

        $attributes = $session->toArray();

        $this->assertArrayNotHasKey(
            'session_id',
            $attributes
        );
    }

    public function test_authentication_session_can_be_soft_deleted(): void
    {
        $user = User::factory()->create();

        $session = AuthenticationSession::query()->create([
            'user_id' => $user->id,
            'session_id' => 'test-session-id',
            'authenticated_at' => now(),
        ]);

        $session->delete();

        $this->assertSoftDeleted(
            'authentication_sessions',
            [
                'id' => $session->id,
            ]
        );
    }

    public function test_deleted_authentication_session_is_not_active(): void
    {
        $user = User::factory()->create();

        $session = AuthenticationSession::query()->create([
            'user_id' => $user->id,
            'session_id' => 'test-session-id',
            'authenticated_at' => now(),
        ]);

        $session->delete();

        $session->refresh();

        $this->assertFalse($session->isActive());
    }

    // test added
    public function test_current_session_returns_the_latest_active_session(): void
    {
        $user = User::factory()->create([
            'email' => 'current-session@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $firstContext = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-current-001',
            ipAddress: '127.0.0.251',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $service->authenticate($firstContext);

        $secondContext = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-current-002',
            ipAddress: '127.0.0.252',
            userAgent: 'Mozilla/5.0',
            browser: 'Chrome',
            device: 'Laptop',
        );

        $service->authenticate($secondContext);

        $current = app(SessionService::class)->current($user);

        $this->assertNotNull($current);
        $this->assertSame('session-current-002', $current->session_id);
    }

    public function test_current_session_ignores_revoked_sessions(): void
    {
        $user = User::factory()->create([
            'email' => 'current-revoked@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $firstContext = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-current-revoked-001',
            ipAddress: '127.0.0.253',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $service->authenticate($firstContext);

        $secondContext = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-current-revoked-002',
            ipAddress: '127.0.0.254',
            userAgent: 'Mozilla/5.0',
            browser: 'Chrome',
            device: 'Laptop',
        );

        $service->authenticate($secondContext);

        $latestSession = AuthenticationSession::query()
            ->where('session_id', 'session-current-revoked-002')
            ->firstOrFail();

        app(SessionService::class)->revoke(
            session: $latestSession,
            reason: 'logout',
        );

        $current = app(SessionService::class)->current($user);

        $this->assertNotNull($current);
        $this->assertSame(
            'session-current-revoked-001',
            $current->session_id
        );
    }

    public function test_current_session_returns_null_when_user_has_no_active_session(): void
    {
        $user = User::factory()->create([
            'email' => 'no-active-session@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $current = app(SessionService::class)->current($user);

        $this->assertNull($current);
    }

    public function test_revoked_session_is_not_counted_as_active(): void
    {
        $user = User::factory()->create([
            'email' => 'revoked-not-active@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $firstContext = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-active-check-001',
            ipAddress: '127.0.0.249',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $service->authenticate($firstContext);

        $secondContext = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-active-check-002',
            ipAddress: '127.0.0.250',
            userAgent: 'Mozilla/5.0',
            browser: 'Chrome',
            device: 'Laptop',
        );

        $service->authenticate($secondContext);

        $session = AuthenticationSession::query()
            ->where('session_id', 'session-active-check-001')
            ->firstOrFail();

        app(SessionService::class)->revoke(
            session: $session,
            reason: 'logout',
        );

        $activeSessions = AuthenticationSession::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->count();

        $this->assertSame(1, $activeSessions);
    }

    public function test_revoking_one_session_keeps_other_sessions_active(): void
    {
        $user = User::factory()->create([
            'email' => 'individual-revocation@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $firstContext = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-revoke-one-001',
            ipAddress: '127.0.0.204',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $service->authenticate($firstContext);

        $secondContext = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-revoke-one-002',
            ipAddress: '127.0.0.205',
            userAgent: 'Mozilla/5.0',
            browser: 'Chrome',
            device: 'Laptop',
        );

        $service->authenticate($secondContext);

        $firstSession = AuthenticationSession::query()
            ->where('session_id', 'session-revoke-one-001')
            ->firstOrFail();

        app(SessionService::class)->revoke(
            session: $firstSession,
            reason: 'logout',
        );

        $this->assertNotNull(
            AuthenticationSession::query()
                ->where('session_id', 'session-revoke-one-001')
                ->firstOrFail()
                ->revoked_at
        );

        $this->assertDatabaseHas('authentication_sessions', [
            'session_id' => 'session-revoke-one-002',
            'revoked_at' => null,
        ]);

        $this->assertSame(
            1,
            AuthenticationSession::query()
                ->where('user_id', $user->id)
                ->whereNull('revoked_at')
                ->count()
        );
    }

    public function test_revoking_an_already_revoked_session_returns_false(): void
    {
        $user = User::factory()->create([
            'email' => 'already-revoked@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $context = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-already-revoked-001',
            ipAddress: '127.0.0.206',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $service->authenticate($context);

        $session = AuthenticationSession::query()
            ->where('session_id', 'session-already-revoked-001')
            ->firstOrFail();

        $sessionService = app(SessionService::class);

        $firstRevocation = $sessionService->revoke(
            session: $session,
            reason: 'logout',
        );

        $session->refresh();

        $secondRevocation = $sessionService->revoke(
            session: $session,
            reason: 'logout',
        );

        $this->assertTrue($firstRevocation);
        $this->assertFalse($secondRevocation);

        $this->assertNotNull($session->revoked_at);
    }

    public function test_deleted_session_cannot_be_revoked(): void
    {
        $user = User::factory()->create([
            'email' => 'deleted-session@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $context = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-deleted-001',
            ipAddress: '127.0.0.207',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $service->authenticate($context);

        $session = AuthenticationSession::query()
            ->where('session_id', 'session-deleted-001')
            ->firstOrFail();

        $session->delete();

        $session->refresh();

        $result = app(SessionService::class)->revoke(
            session: $session,
            reason: 'logout',
        );

        $this->assertFalse($result);
        $this->assertNotNull($session->deleted_at);
    }

    public function test_current_session_ignores_deleted_sessions(): void
    {
        $user = User::factory()->create([
            'email' => 'current-deleted@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $firstContext = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-current-deleted-001',
            ipAddress: '127.0.0.208',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $service->authenticate($firstContext);

        $secondContext = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-current-deleted-002',
            ipAddress: '127.0.0.209',
            userAgent: 'Mozilla/5.0',
            browser: 'Chrome',
            device: 'Laptop',
        );

        $service->authenticate($secondContext);

        $latestSession = AuthenticationSession::query()
            ->where('session_id', 'session-current-deleted-002')
            ->firstOrFail();

        $latestSession->delete();

        $current = app(SessionService::class)->current($user);

        $this->assertNotNull($current);
        $this->assertSame(
            'session-current-deleted-001',
            $current->session_id
        );
    }

    public function test_current_session_returns_latest_active_session_when_latest_session_is_revoked(): void
    {
        $user = User::factory()->create([
            'email' => 'latest-revoked-current@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $firstContext = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-latest-revoked-001',
            ipAddress: '127.0.0.212',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $service->authenticate($firstContext);

        $secondContext = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-latest-revoked-002',
            ipAddress: '127.0.0.213',
            userAgent: 'Mozilla/5.0',
            browser: 'Chrome',
            device: 'Laptop',
        );

        $service->authenticate($secondContext);

        $latestSession = AuthenticationSession::query()
            ->where('session_id', 'session-latest-revoked-002')
            ->firstOrFail();

        app(SessionService::class)->revoke(
            session: $latestSession,
            reason: 'logout',
        );

        $current = app(SessionService::class)->current($user);

        $this->assertNotNull($current);
        $this->assertSame(
            'session-latest-revoked-001',
            $current->session_id
        );
        $this->assertNull($current->revoked_at);
    }

    public function test_revoked_session_keeps_its_revocation_reason(): void
    {
        $user = User::factory()->create([
            'email' => 'revocation-reason@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $context = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-reason-001',
            ipAddress: '127.0.0.216',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $service->authenticate($context);

        $session = AuthenticationSession::query()
            ->where('session_id', 'session-reason-001')
            ->firstOrFail();

        $reason = 'security_review';

        $result = app(SessionService::class)->revoke(
            session: $session,
            reason: $reason,
        );

        $session->refresh();

        $this->assertTrue($result);
        $this->assertNotNull($session->revoked_at);
        $this->assertSame($reason, $session->revocation_reason);

        $this->assertDatabaseHas('authentication_sessions', [
            'session_id' => 'session-reason-001',
            'revocation_reason' => $reason,
        ]);
    }

    public function test_custom_session_revocation_records_session_revoked_history(): void
    {
        $user = User::factory()->create([
            'email' => 'session-revoked-history@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $context = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-history-001',
            ipAddress: '127.0.0.217',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $service->authenticate($context);

        $session = AuthenticationSession::query()
            ->where('session_id', 'session-history-001')
            ->firstOrFail();

        app(SessionService::class)->revoke(
            session: $session,
            reason: 'security_review',
        );

        $this->assertDatabaseHas('login_histories', [
            'user_id' => $user->id,
            'ip_address' => '127.0.0.217',
            'browser' => 'Firefox',
            'device' => 'Desktop',
        ]);
    }

    public function test_custom_session_revocation_does_not_record_logout_history_event(): void
    {
        $user = User::factory()->create([
            'email' => 'custom-revocation-no-logout@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $context = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-no-logout-001',
            ipAddress: '127.0.0.218',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $service->authenticate($context);

        $session = AuthenticationSession::query()
            ->where('session_id', 'session-no-logout-001')
            ->firstOrFail();

        app(SessionService::class)->revoke(
            session: $session,
            reason: 'security_review',
        );

        $this->assertDatabaseMissing('login_histories', [
            'user_id' => $user->id,
            'event' => 'logout',
        ]);
    }

    public function test_revoke_for_user_revokes_all_active_sessions(): void
    {
        $user = User::factory()->create([
            'email' => 'revoke-all@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $contexts = [
            new AuthenticationContext(
                email: $user->email,
                password: 'ValidPassword123',
                sessionId: 'session-revoke-all-001',
                ipAddress: '127.0.0.220',
                userAgent: 'Mozilla/5.0',
                browser: 'Firefox',
                device: 'Desktop',
            ),
            new AuthenticationContext(
                email: $user->email,
                password: 'ValidPassword123',
                sessionId: 'session-revoke-all-002',
                ipAddress: '127.0.0.221',
                userAgent: 'Mozilla/5.0',
                browser: 'Chrome',
                device: 'Laptop',
            ),
            new AuthenticationContext(
                email: $user->email,
                password: 'ValidPassword123',
                sessionId: 'session-revoke-all-003',
                ipAddress: '127.0.0.222',
                userAgent: 'Mozilla/5.0',
                browser: 'Safari',
                device: 'Mobile',
            ),
        ];

        foreach ($contexts as $context) {
            $service->authenticate($context);
        }

        $result = app(SessionService::class)->revokeForUser(
            user: $user,
            reason: 'global_logout',
        );

        $this->assertTrue($result);

        $this->assertSame(
            0,
            AuthenticationSession::query()
                ->where('user_id', $user->id)
                ->whereNull('revoked_at')
                ->count()
        );

        $this->assertSame(
            3,
            AuthenticationSession::query()
                ->where('user_id', $user->id)
                ->whereNotNull('revoked_at')
                ->count()
        );
    }

    public function test_revoke_for_user_returns_false_when_no_active_session_exists(): void
    {
        $user = User::factory()->create([
            'email' => 'revoke-all-empty@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $result = app(SessionService::class)->revokeForUser(
            user: $user,
            reason: 'global_logout',
        );

        $this->assertFalse($result);

        $this->assertSame(
            0,
            AuthenticationSession::query()
                ->where('user_id', $user->id)
                ->count()
        );
    }

    public function test_revoke_for_user_ignores_already_revoked_sessions(): void
    {
        $user = User::factory()->create([
            'email' => 'revoke-all-existing@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $firstContext = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-revoke-existing-001',
            ipAddress: '127.0.0.223',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $service->authenticate($firstContext);

        $secondContext = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-revoke-existing-002',
            ipAddress: '127.0.0.224',
            userAgent: 'Mozilla/5.0',
            browser: 'Chrome',
            device: 'Laptop',
        );

        $service->authenticate($secondContext);

        $firstSession = AuthenticationSession::query()
            ->where('session_id', 'session-revoke-existing-001')
            ->firstOrFail();

        app(SessionService::class)->revoke(
            session: $firstSession,
            reason: 'logout',
        );

        $revokedAt = $firstSession->fresh()->revoked_at;

        $result = app(SessionService::class)->revokeForUser(
            user: $user,
            reason: 'global_logout',
        );

        $this->assertTrue($result);

        $firstSession->refresh();

        $this->assertTrue(
            $firstSession->revoked_at->equalTo($revokedAt)
        );

        $this->assertNotNull(
            AuthenticationSession::query()
                ->where('session_id', 'session-revoke-existing-002')
                ->firstOrFail()
                ->revoked_at
        );
    }

    public function test_revoke_for_user_ignores_deleted_sessions(): void
    {
        $user = User::factory()->create([
            'email' => 'revoke-all-deleted@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $context = new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-revoke-deleted-001',
            ipAddress: '127.0.0.225',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $service->authenticate($context);

        $session = AuthenticationSession::query()
            ->where('session_id', 'session-revoke-deleted-001')
            ->firstOrFail();

        $session->delete();

        $result = app(SessionService::class)->revokeForUser(
            user: $user,
            reason: 'global_logout',
        );

        $this->assertFalse($result);

        $this->assertNotNull($session->fresh()->deleted_at);
    }

    public function test_revoke_for_user_does_not_revoke_another_users_sessions(): void
    {
        $user = User::factory()->create([
            'email' => 'revoke-owner@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $otherUser = User::factory()->create([
            'email' => 'revoke-other@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $service->authenticate(new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-owner-001',
            ipAddress: '127.0.0.226',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        ));

        $service->authenticate(new AuthenticationContext(
            email: $otherUser->email,
            password: 'ValidPassword123',
            sessionId: 'session-other-001',
            ipAddress: '127.0.0.227',
            userAgent: 'Mozilla/5.0',
            browser: 'Chrome',
            device: 'Laptop',
        ));

        app(SessionService::class)->revokeForUser(
            user: $user,
            reason: 'global_logout',
        );

        $this->assertSame(
            0,
            AuthenticationSession::query()
                ->where('user_id', $user->id)
                ->whereNull('revoked_at')
                ->count()
        );

        $this->assertSame(
            1,
            AuthenticationSession::query()
                ->where('user_id', $otherUser->id)
                ->whereNull('revoked_at')
                ->count()
        );

        $this->assertNotNull(
            AuthenticationSession::query()
                ->where('user_id', $user->id)
                ->where('session_id', 'session-owner-001')
                ->firstOrFail()
                ->revoked_at
        );

        $this->assertNull(
            AuthenticationSession::query()
                ->where('user_id', $otherUser->id)
                ->where('session_id', 'session-other-001')
                ->firstOrFail()
                ->revoked_at
        );
    }

    public function test_revoke_for_user_does_not_change_existing_revocation_reason(): void
    {
        $user = User::factory()->create([
            'email' => 'revoke-reason-preserved@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $service->authenticate(new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-reason-preserved-001',
            ipAddress: '127.0.0.228',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        ));

        $service->authenticate(new AuthenticationContext(
            email: $user->email,
            password: 'ValidPassword123',
            sessionId: 'session-reason-preserved-002',
            ipAddress: '127.0.0.229',
            userAgent: 'Mozilla/5.0',
            browser: 'Chrome',
            device: 'Laptop',
        ));

        $alreadyRevoked = AuthenticationSession::query()
            ->where('session_id', 'session-reason-preserved-001')
            ->firstOrFail();

        app(SessionService::class)->revoke(
            session: $alreadyRevoked,
            reason: 'security_review',
        );

        app(SessionService::class)->revokeForUser(
            user: $user,
            reason: 'global_logout',
        );

        $alreadyRevoked->refresh();

        $this->assertSame(
            'security_review',
            $alreadyRevoked->revocation_reason
        );

        $this->assertNotNull($alreadyRevoked->revoked_at);

        $this->assertSame(
            'global_logout',
            AuthenticationSession::query()
                ->where('session_id', 'session-reason-preserved-002')
                ->firstOrFail()
                ->revocation_reason
        );
    }

    public function test_revoke_for_user_returns_true_when_an_active_session_is_revoked(): void
    {
        $user = User::factory()->create([
            'email' => 'revoke-return-true@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        app(AuthenticationService::class)->authenticate(
            new AuthenticationContext(
                email: $user->email,
                password: 'ValidPassword123',
                sessionId: 'session-revoke-return-true-001',
                ipAddress: '127.0.0.230',
                userAgent: 'Mozilla/5.0',
                browser: 'Firefox',
                device: 'Desktop',
            )
        );

        $result = app(SessionService::class)->revokeForUser(
            user: $user,
            reason: 'global_logout',
        );

        $this->assertTrue($result);
    }

    public function test_revoke_for_user_revokes_all_active_sessions_and_returns_true(): void
    {
        $user = User::factory()->create([
            'email' => 'revoke-all-return-true@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $service->authenticate(
            new AuthenticationContext(
                email: $user->email,
                password: 'ValidPassword123',
                sessionId: 'session-revoke-all-001',
                ipAddress: '127.0.0.231',
                userAgent: 'Mozilla/5.0',
                browser: 'Firefox',
                device: 'Desktop',
            )
        );

        $service->authenticate(
            new AuthenticationContext(
                email: $user->email,
                password: 'ValidPassword123',
                sessionId: 'session-revoke-all-002',
                ipAddress: '127.0.0.232',
                userAgent: 'Mozilla/5.0',
                browser: 'Chrome',
                device: 'Mobile',
            )
        );

        $result = app(SessionService::class)->revokeForUser(
            user: $user,
            reason: 'global_logout',
        );

        $this->assertTrue($result);

        $this->assertDatabaseHas('authentication_sessions', [
            'session_id' => 'session-revoke-all-001',
            'revocation_reason' => 'global_logout',
        ]);

        $this->assertDatabaseHas('authentication_sessions', [
            'session_id' => 'session-revoke-all-002',
            'revocation_reason' => 'global_logout',
        ]);
    }

    public function test_revoke_for_user_does_not_record_individual_logout_history_events(): void
    {
        $user = User::factory()->create([
            'email' => 'global-logout-history@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $service->authenticate(
            new AuthenticationContext(
                email: $user->email,
                password: 'ValidPassword123',
                sessionId: 'session-global-history-001',
                ipAddress: '127.0.0.233',
                userAgent: 'Mozilla/5.0',
                browser: 'Firefox',
                device: 'Desktop',
            )
        );

        $service->authenticate(
            new AuthenticationContext(
                email: $user->email,
                password: 'ValidPassword123',
                sessionId: 'session-global-history-002',
                ipAddress: '127.0.0.234',
                userAgent: 'Mozilla/5.0',
                browser: 'Chrome',
                device: 'Mobile',
            )
        );

        $result = app(SessionService::class)->revokeForUser(
            user: $user,
            reason: 'global_logout',
        );

        $this->assertTrue($result);

        $this->assertDatabaseMissing('login_histories', [
            'user_id' => $user->id,
            'event' => 'logout',
        ]);

        $this->assertDatabaseHas('login_histories', [
            'user_id' => $user->id,
            'event' => 'session_revoked',
        ]);
    }

    public function test_revoke_for_user_preserves_global_logout_reason_on_all_sessions(): void
    {
        $user = User::factory()->create([
            'email' => 'global-logout-reason@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $service->authenticate(
            new AuthenticationContext(
                email: $user->email,
                password: 'ValidPassword123',
                sessionId: 'session-global-reason-001',
                ipAddress: '127.0.0.235',
                userAgent: 'Mozilla/5.0',
                browser: 'Firefox',
                device: 'Desktop',
            )
        );

        $service->authenticate(
            new AuthenticationContext(
                email: $user->email,
                password: 'ValidPassword123',
                sessionId: 'session-global-reason-002',
                ipAddress: '127.0.0.236',
                userAgent: 'Mozilla/5.0',
                browser: 'Chrome',
                device: 'Mobile',
            )
        );

        $result = app(SessionService::class)->revokeForUser(
            user: $user,
            reason: 'global_logout',
        );

        $this->assertTrue($result);

        $this->assertDatabaseHas('authentication_sessions', [
            'session_id' => 'session-global-reason-001',
            'revocation_reason' => 'global_logout',
        ]);

        $this->assertDatabaseHas('authentication_sessions', [
            'session_id' => 'session-global-reason-002',
            'revocation_reason' => 'global_logout',
        ]);
    }

    public function test_revoke_for_user_returns_false_when_all_sessions_are_already_revoked(): void
    {
        $user = User::factory()->create([
            'email' => 'all-sessions-revoked@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $service->authenticate(
            new AuthenticationContext(
                email: $user->email,
                password: 'ValidPassword123',
                sessionId: 'session-all-revoked-001',
                ipAddress: '127.0.0.237',
                userAgent: 'Mozilla/5.0',
                browser: 'Firefox',
                device: 'Desktop',
            )
        );

        $session = AuthenticationSession::query()
            ->where('session_id', 'session-all-revoked-001')
            ->firstOrFail();

        $session->revoked_at = now();
        $session->revocation_reason = 'logout';
        $session->save();

        $result = app(SessionService::class)->revokeForUser(
            user: $user,
            reason: 'global_logout',
        );

        $this->assertFalse($result);

        $this->assertDatabaseHas('authentication_sessions', [
            'session_id' => 'session-all-revoked-001',
            'revocation_reason' => 'logout',
        ]);
    }

    public function test_revoke_for_user_does_not_revoke_sessions_of_another_user(): void
    {
        $user = User::factory()->create([
            'email' => 'global-logout-owner@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $otherUser = User::factory()->create([
            'email' => 'global-logout-other@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $service->authenticate(
            new AuthenticationContext(
                email: $user->email,
                password: 'ValidPassword123',
                sessionId: 'session-owner-001',
                ipAddress: '127.0.0.238',
                userAgent: 'Mozilla/5.0',
                browser: 'Firefox',
                device: 'Desktop',
            )
        );

        $service->authenticate(
            new AuthenticationContext(
                email: $otherUser->email,
                password: 'ValidPassword123',
                sessionId: 'session-other-001',
                ipAddress: '127.0.0.239',
                userAgent: 'Mozilla/5.0',
                browser: 'Chrome',
                device: 'Mobile',
            )
        );

        $result = app(SessionService::class)->revokeForUser(
            user: $user,
            reason: 'global_logout',
        );

        $this->assertTrue($result);

        $this->assertDatabaseHas('authentication_sessions', [
            'session_id' => 'session-owner-001',
            'revocation_reason' => 'global_logout',
        ]);

        $this->assertDatabaseHas('authentication_sessions', [
            'session_id' => 'session-other-001',
            'revoked_at' => null,
            'revocation_reason' => null,
        ]);
    }

    public function test_revoke_for_user_revokes_only_active_sessions(): void
    {
        $user = User::factory()->create([
            'email' => 'global-logout-mixed-sessions@example.com',
            'password' => Hash::make('ValidPassword123'),
            'status' => UserStatus::Active,
        ]);

        $service = app(AuthenticationService::class);

        $service->authenticate(
            new AuthenticationContext(
                email: $user->email,
                password: 'ValidPassword123',
                sessionId: 'session-mixed-active-001',
                ipAddress: '127.0.0.241',
                userAgent: 'Mozilla/5.0',
                browser: 'Firefox',
                device: 'Desktop',
            )
        );

        $service->authenticate(
            new AuthenticationContext(
                email: $user->email,
                password: 'ValidPassword123',
                sessionId: 'session-mixed-revoked-001',
                ipAddress: '127.0.0.242',
                userAgent: 'Mozilla/5.0',
                browser: 'Chrome',
                device: 'Mobile',
            )
        );

        $revokedSession = AuthenticationSession::query()
            ->where('session_id', 'session-mixed-revoked-001')
            ->firstOrFail();

        $revokedSession->revoked_at = now();
        $revokedSession->revocation_reason = 'logout';
        $revokedSession->save();

        $result = app(SessionService::class)->revokeForUser(
            user: $user,
            reason: 'global_logout',
        );

        $this->assertTrue($result);

        $this->assertDatabaseHas('authentication_sessions', [
            'session_id' => 'session-mixed-active-001',
            'revocation_reason' => 'global_logout',
        ]);

        $this->assertDatabaseHas('authentication_sessions', [
            'session_id' => 'session-mixed-revoked-001',
            'revocation_reason' => 'logout',
        ]);
    }
}