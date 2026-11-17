<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Authentication\Models;

use App\Domains\Identity\Authentication\Models\LoginHistory;
use App\Domains\Identity\Authentication\Services\LoginHistoryService;
use App\Domains\Identity\Authentication\Services\SessionService;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use LogicException;
use Illuminate\Support\Carbon;
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
            Carbon::class,
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

    public function test_login_history_is_immutable_through_eloquent(): void
    {
        $history = LoginHistory::factory()->create();

        try {
            $history->event = 'altered';
            $history->save();
            self::fail('Expected immutable login history update to throw.');
        } catch (LogicException $exception) {
            self::assertSame('Login history is append-only.', $exception->getMessage());
        }

        $this->expectException(LogicException::class);
        $history->delete();
    }

    public function test_database_rejects_direct_login_history_updates_and_deletes(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            self::markTestSkipped('Database-level append-only triggers are configured for MySQL.');
        }

        $history = LoginHistory::factory()->create();

        try {
            DB::table('login_histories')->where('id', $history->getKey())->update(['event' => 'altered']);
            self::fail('Expected database trigger to reject login history update.');
        } catch (QueryException) {
            $this->assertDatabaseHas('login_histories', ['id' => $history->getKey(), 'event' => $history->event]);
        }

        $this->expectException(QueryException::class);
        DB::table('login_histories')->where('id', $history->getKey())->delete();
    }
}
