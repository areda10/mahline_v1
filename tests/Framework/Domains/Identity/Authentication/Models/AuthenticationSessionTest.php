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
}