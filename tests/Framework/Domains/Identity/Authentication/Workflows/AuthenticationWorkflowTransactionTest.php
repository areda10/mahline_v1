<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Authentication\Workflows;

use App\Domains\Identity\Authentication\Models\AuthenticationSession;
use App\Domains\Identity\Authentication\Workflows\AuthenticationWorkflowHook;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

final class AuthenticationWorkflowTransactionTest extends TestCase
{
    use RefreshDatabase;

    private string $loginUrl;

    private string $userAgent =
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
        . 'AppleWebKit/537.36 (KHTML, like Gecko) '
        . 'Chrome/140.0.0.0 Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();

        $this->loginUrl = route('authentication.login.store');
    }

    public function test_device_and_session_creation_are_atomic(): void
    {
        $user = User::factory()->create([
            'email' => 'atomicity@example.com',
            'password' => 'Password123',
            'status' => 'active',
        ]);

        $response = $this
            ->withHeader('User-Agent', $this->userAgent)
            ->postJson($this->loginUrl, [
                'email' => $user->email,
                'password' => 'Password123',
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('authentication_sessions', [
            'user_id' => $user->getKey(),
        ]);

        $this->assertDatabaseHas('known_devices', [
            'user_id' => $user->getKey(),
            'device' => 'windows',
        ]);
    }

    public function test_failed_session_creation_does_not_remember_new_device(): void
    {
        $user = User::factory()->create([
            'email' => 'atomic-failure@example.com',
            'password' => 'ValidPassword123',
            'status' => 'active',
        ]);

        $failingHook = new class implements AuthenticationWorkflowHook
        {
            public function afterDeviceRemembered(
                User $user,
                AuthenticationSession $session,
            ): void {
                throw new RuntimeException(
                    'Controlled authentication workflow failure.',
                );
            }
        };

        $this->app->instance(
            AuthenticationWorkflowHook::class,
            $failingHook,
        );

        $response = $this
            ->withHeader('User-Agent', $this->userAgent)
            ->postJson($this->loginUrl, [
                'email' => $user->email,
                'password' => 'ValidPassword123',
            ]);

        $response->assertStatus(401);

        $response->assertJson([
            'message' => 'Controlled authentication workflow failure.',
        ]);

        $this->assertDatabaseMissing('authentication_sessions', [
            'user_id' => $user->getKey(),
        ]);

        $this->assertDatabaseMissing('known_devices', [
            'user_id' => $user->getKey(),
            'device' => 'windows',
        ]);

        $this->assertDatabaseMissing('login_histories', [
            'user_id' => $user->getKey(),
            'event' => 'success',
        ]);
    }
}
