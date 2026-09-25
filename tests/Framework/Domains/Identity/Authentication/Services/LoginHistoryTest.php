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
use ReflectionNamedType;
use ReflectionClass;
use Tests\TestCase;

final class LoginHistoryTest extends TestCase
{
    use RefreshDatabase;

    private LoginHistoryService $loginHistoryService;

    private SessionService $sessionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loginHistoryService = app(
            LoginHistoryService::class
        );

        $this->sessionService = app(
            SessionService::class
        );
    }

    /**
     * A successful login can be recorded.
     */
    public function test_records_successful_login(): void
    {
        $user = User::factory()->create();

        $session = $this->sessionService->create(
            user: $user,
            sessionId: 'session-success',
            ipAddress: '127.0.0.1',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
        );

        $history = $this->loginHistoryService->recordSuccess(
            user: $user,
            session: $session,
            ipAddress: '127.0.0.1',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $this->assertInstanceOf(
            LoginHistory::class,
            $history
        );

        $this->assertSame(
            $user->getKey(),
            $history->user_id
        );

        $this->assertSame(
            $user->email,
            $history->email
        );

        $this->assertSame(
            'success',
            $history->event
        );

        $this->assertNull(
            $history->reason
        );

        $this->assertSame(
            $session->getKey(),
            $history->authentication_session_id
        );
    }

    /**
     * A failed authentication can be recorded.
     */
    public function test_records_failed_login(): void
    {
        $user = User::factory()->create();

        $history = $this->loginHistoryService->recordFailure(
            email: $user->email,
            reason: 'invalid_credentials',
            user: $user,
            ipAddress: '127.0.0.1',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Desktop',
        );

        $this->assertSame(
            $user->getKey(),
            $history->user_id
        );

        $this->assertSame(
            $user->email,
            $history->email
        );

        $this->assertSame(
            'failed',
            $history->event
        );

        $this->assertSame(
            'invalid_credentials',
            $history->reason
        );

        $this->assertNull(
            $history->authentication_session_id
        );
    }

    /**
     * An unknown user attempt can be recorded.
     */
    public function test_records_unknown_user_attempt(): void
    {
        $email = 'unknown@example.com';

        $history = $this->loginHistoryService->recordFailure(
            email: $email,
            reason: 'unknown_user',
        );

        $this->assertNull(
            $history->user_id
        );

        $this->assertSame(
            $email,
            $history->email
        );

        $this->assertSame(
            'failed',
            $history->event
        );

        $this->assertSame(
            'unknown_user',
            $history->reason
        );

        $this->assertNull(
            $history->authentication_session_id
        );
    }

    /**
     * A logout event can be recorded.
     */
    public function test_records_logout(): void
    {
        $user = User::factory()->create();

        $session = $this->sessionService->create(
            user: $user,
            sessionId: 'session-logout',
        );

        $history = $this->loginHistoryService->recordLogout(
            user: $user,
            session: $session,
        );

        $this->assertSame(
            'logout',
            $history->event
        );

        $this->assertSame(
            'logout',
            $history->reason
        );

        $this->assertSame(
            $session->getKey(),
            $history->authentication_session_id
        );
    }

    /**
     * A session revocation can be recorded.
     */
    public function test_records_session_revocation(): void
    {
        $user = User::factory()->create();

        $session = $this->sessionService->create(
            user: $user,
            sessionId: 'session-revoked',
        );

        $history = $this->loginHistoryService->recordSessionRevoked(
            user: $user,
            session: $session,
            reason: 'new_login',
        );

        $this->assertSame(
            'session_revoked',
            $history->event
        );

        $this->assertSame(
            'new_login',
            $history->reason
        );

        $this->assertSame(
            $session->getKey(),
            $history->authentication_session_id
        );
    }

    /**
     * Client information is persisted.
     */
    public function test_records_client_information(): void
    {
        $user = User::factory()->create();

        $history = $this->loginHistoryService->recordFailure(
            email: $user->email,
            user: $user,
            ipAddress: '192.168.1.10',
            userAgent: 'Mozilla/5.0',
            browser: 'Safari',
            device: 'iPhone',
        );

        $this->assertSame(
            '192.168.1.10',
            $history->ip_address
        );

        $this->assertSame(
            'Mozilla/5.0',
            $history->user_agent
        );

        $this->assertSame(
            'Safari',
            $history->browser
        );

        $this->assertSame(
            'iPhone',
            $history->device
        );
    }

    /**
     * occurred_at is cast to a Carbon date.
     */
    public function test_occurred_at_is_cast_to_datetime(): void
    {
        $history = LoginHistory::factory()->create();

        $this->assertInstanceOf(
            \Illuminate\Support\Carbon::class,
            $history->occurred_at
        );
    }

    /**
     * Login history belongs to a user.
     */
    public function test_login_history_belongs_to_user(): void
    {
        $user = User::factory()->create();

        $history = LoginHistory::factory()->create([
            'user_id' => $user->getKey(),
            'email' => $user->email,
        ]);

        $this->assertTrue(
            $history->user->is($user)
        );
    }

    /**
     * Login history belongs to an authentication session.
     */
    public function test_login_history_belongs_to_authentication_session(): void
    {
        $user = User::factory()->create();

        $session = $this->sessionService->create(
            user: $user,
            sessionId: 'session-history',
        );

        $history = LoginHistory::factory()
            ->forSession($session)
            ->create();

        $this->assertTrue(
            $history->authenticationSession->is($session)
        );
    }

    /**
     * User-Agent is hidden from array representation.
     */
    public function test_user_agent_is_hidden(): void
    {
        $history = LoginHistory::factory()->create();

        $array = $history->toArray();

        $this->assertArrayNotHasKey(
            'user_agent',
            $array
        );
    }

    /**
     * Login history supports soft deletion.
     */
    public function test_login_history_can_be_soft_deleted(): void
    {
        $history = LoginHistory::factory()->create();

        $history->delete();

        $this->assertSoftDeleted(
            'login_histories',
            [
                'id' => $history->getKey(),
            ]
        );
    }

    /**
     * LoginHistoryService must never depend directly
     * on the HTTP Request.
     */
    public function test_login_history_service_does_not_depend_on_request(): void
    {
        $reflection = new ReflectionClass(
            LoginHistoryService::class
        );

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            $this->assertTrue(true);

            return;
        }

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            if (! $type instanceof ReflectionNamedType) {
                continue;
            }

            $this->assertNotSame(
                Request::class,
                $type->getName()
            );
        }

        $this->assertTrue(true);
    }

    /**
     * The plain-text password must never be stored in LoginHistory.
     */
    public function test_plain_password_is_not_stored_in_login_history(): void
    {
        $user = User::factory()->create([
            'email' => 'security@example.com',
            'password' => 'password',
            'status' => UserStatus::Active,
        ]);

        $this->postJson(
            $this->loginUrl,
            [
                'email' => $user->email,
                'password' => 'password',
            ],
        )->assertOk();

        /*
         * LoginHistory must never contain a password column/value.
         */
        $history = LoginHistory::query()
            ->where('user_id', $user->getKey())
            ->firstOrFail();

        $attributes = $history->getAttributes();

        foreach ($attributes as $value) {
            if (is_string($value)) {
                $this->assertStringNotContainsString(
                    'password',
                    strtolower($value),
                );
            }
        }
    }
}