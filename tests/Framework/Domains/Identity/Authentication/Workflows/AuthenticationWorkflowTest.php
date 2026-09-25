<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Authentication\Workflows;

use App\Domains\Identity\Authentication\Models\AuthenticationSession;
use App\Domains\Identity\Authentication\Models\LoginHistory;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

final class AuthenticationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private string $loginUrl;

    private string $logoutUrl;

    private string $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/140.0.0.0 Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();

        $this->loginUrl = route('authentication.login.store');
        $this->logoutUrl = route('authentication.logout');
    }

    public function test_successful_authentication_creates_active_session(): void
    {
        $user = User::factory()->create([
            'email' => 'workflow@example.com',
            'password' => 'password',
        ]);

        $response = $this->withHeader(
            'User-Agent',
            $this->userAgent,
        )->postJson($this->loginUrl, [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Authentication successful.',
            ]);

        $this->assertDatabaseCount(
            'authentication_sessions',
            1,
        );

        $session = AuthenticationSession::query()
            ->where('user_id', $user->getKey())
            ->first();

        $this->assertNotNull($session);
        $this->assertNull($session->revoked_at);
        $this->assertNotNull($session->authenticated_at);
        $this->assertNotNull($session->last_activity_at);
        $this->assertNotEmpty($session->session_id);
    }

    public function test_successful_authentication_creates_login_history(): void
    {
        $user = User::factory()->create([
            'email' => 'history@example.com',
            'password' => 'password',
        ]);

        $response = $this->withHeader(
            'User-Agent',
            $this->userAgent,
        )->postJson($this->loginUrl, [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk();

        $session = AuthenticationSession::query()
            ->where('user_id', $user->getKey())
            ->first();

        $this->assertNotNull($session);

        $this->assertDatabaseHas('login_histories', [
            'user_id' => $user->getKey(),
            'event' => 'success',
            'authentication_session_id' => $session->getKey(),
        ]);
    }

    public function test_logout_only_revokes_current_session(): void
    {
        $user = User::factory()->create([
            'email' => 'logout@example.com',
            'password' => bcrypt('Password123'),
            'status' => 'active',
        ]);

        /*
        * Login through the real HTTP workflow.
        */
        $loginResponse = $this->post($this->loginUrl, [
            'email' => 'logout@example.com',
            'password' => 'Password123',
        ]);

        $loginResponse->assertOk();

        /*
        * Retrieve the authentication session created by the login workflow.
        */
        $firstSession = AuthenticationSession::query()
            ->where('user_id', $user->getKey())
            ->latest('authenticated_at')
            ->firstOrFail();

        /*
        * The Laravel session ID used to create the
        * AuthenticationSession must be reused for logout.
        */
        $sessionId = $this->app['session']->getId();

        $this->assertSame(
            $firstSession->session_id,
            $sessionId,
        );

        /*
        * Reuse the exact Web session through the configured
        * Laravel session cookie.
        */
        $logoutResponse = $this
            ->withCookie('mahline-session', $sessionId)
            ->post($this->logoutUrl);

        $logoutResponse->assertOk()
            ->assertJson([
                'message' => 'Logout successful.',
            ]);

        $firstSession->refresh();

        /*
        * The current authentication session must be revoked.
        */
        $this->assertNotNull($firstSession->revoked_at);

        $this->assertSame(
            'logout',
            $firstSession->revocation_reason,
        );
    }

    public function test_new_login_keeps_previous_session_active(): void
    {
        $user = User::factory()->create([
            'email' => 'multiple-sessions@example.com',
            'password' => 'password',
        ]);

        /*
         * First Web session.
         */
        $firstResponse = $this->withHeader(
            'User-Agent',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/140.0.0.0 Safari/537.36',
        )->postJson($this->loginUrl, [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $firstResponse->assertOk();

        $firstSession = AuthenticationSession::query()
            ->where('user_id', $user->getKey())
            ->firstOrFail();

        /*
         * Second Web session.
         */
        $secondResponse = $this->withHeader(
            'User-Agent',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 15_0) AppleWebKit/605.1.15 Version/18.0 Safari/605.1.15',
        )->postJson($this->loginUrl, [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $secondResponse->assertOk();

        $sessions = AuthenticationSession::query()
            ->where('user_id', $user->getKey())
            ->orderBy('authenticated_at')
            ->get();

        $this->assertCount(2, $sessions);

        $firstSession->refresh();

        $this->assertNull($firstSession->revoked_at);

        $activeSessions = $sessions
            ->filter(
                static fn (AuthenticationSession $session): bool =>
                    $session->revoked_at === null
            );

        $this->assertCount(2, $activeSessions);
    }

    public function test_successful_login_history_references_session(): void
    {
        $user = User::factory()->create([
            'email' => 'session-history@example.com',
            'password' => 'password',
        ]);

        $response = $this->withHeader(
            'User-Agent',
            $this->userAgent,
        )->postJson($this->loginUrl, [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk();

        $session = AuthenticationSession::query()
            ->where('user_id', $user->getKey())
            ->firstOrFail();

        $history = LoginHistory::query()
            ->where('user_id', $user->getKey())
            ->where('event', 'success')
            ->firstOrFail();

        $this->assertSame(
            $session->getKey(),
            $history->authentication_session_id,
        );
    }

    public function test_login_from_phone_keeps_previous_computer_session_active(): void
    {
        $user = User::factory()->create([
            'email' => 'multi-device@example.com',
            'password' => 'password',
        ]);

        $computerUserAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/140.0.0.0 Safari/537.36';

        $phoneUserAgent = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Version/18.0 Mobile/15E148 Safari/604.1';

        /*
         * Computer login.
         */
        $computerResponse = $this->withHeader(
            'User-Agent',
            $computerUserAgent,
        )->postJson($this->loginUrl, [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $computerResponse->assertOk();

        $computerSession = AuthenticationSession::query()
            ->where('user_id', $user->getKey())
            ->where('device', 'Windows')
            ->firstOrFail();

        /*
         * Phone login.
         */
        $phoneResponse = $this->withHeader(
            'User-Agent',
            $phoneUserAgent,
        )->postJson($this->loginUrl, [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $phoneResponse->assertOk();

        $phoneSession = AuthenticationSession::query()
            ->where('user_id', $user->getKey())
            ->where('device', 'iPhone')
            ->firstOrFail();

        $computerSession->refresh();
        $phoneSession->refresh();

        /*
         * Both sessions must remain active.
         */
        $this->assertNull($computerSession->revoked_at);
        $this->assertNull($phoneSession->revoked_at);

        $this->assertNotSame(
            $computerSession->getKey(),
            $phoneSession->getKey(),
        );

        $this->assertNotSame(
            $computerSession->session_id,
            $phoneSession->session_id,
        );
    }
}