<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Authentication\Models;

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

}