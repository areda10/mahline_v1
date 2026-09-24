<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Authentication\Models;

use App\Domains\Identity\Authentication\Models\AuthenticationSession;
use App\Domains\Identity\Authentication\Models\PersonalAccessToken;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class PersonalAccessTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_personal_access_token(): void
    {
        $user = User::factory()->create([
            'status' => UserStatus::Active,
        ]);

        $accessToken = $user->createToken(
            'test-token',
            ['users:read'],
        );

        $this->assertNotEmpty($accessToken->plainTextToken);

        $token = PersonalAccessToken::query()
            ->where('id', $accessToken->accessToken->getKey())
            ->first();

        $this->assertNotNull($token);
        $this->assertSame($user->getKey(), $token->tokenable_id);
        $this->assertSame(User::class, $token->tokenable_type);
        $this->assertSame('test-token', $token->name);
        $this->assertSame(['users:read'], $token->abilities);
    }

    public function test_personal_access_token_uses_ulid(): void
    {
        $user = User::factory()->create([
            'status' => UserStatus::Active,
        ]);

        $accessToken = $user->createToken('ulid-test');

        $token = $accessToken->accessToken;

        $this->assertInstanceOf(
            PersonalAccessToken::class,
            $token,
        );

        $this->assertIsString($token->getKey());
        $this->assertSame(26, strlen($token->getKey()));
        $this->assertSame('string', $token->getKeyType());
        $this->assertFalse($token->incrementing);
    }

    public function test_personal_access_token_is_stored_hashed(): void
    {
        $user = User::factory()->create([
            'status' => UserStatus::Active,
        ]);

        $accessToken = $user->createToken('hash-test');

        $plainTextToken = $accessToken->plainTextToken;
        $storedToken = $accessToken->accessToken->token;

        $this->assertNotSame($plainTextToken, $storedToken);

        [, $tokenSecret] = explode('|', $plainTextToken, 2);

        $this->assertSame(
            hash('sha256', $tokenSecret),
            $storedToken,
        );
    }

    public function test_personal_access_token_model_is_registered_with_sanctum(): void
    {
        $this->assertSame(
            PersonalAccessToken::class,
            Sanctum::personalAccessTokenModel(),
        );
    }

    public function test_api_token_creation_does_not_create_web_session(): void
    {
        $user = User::factory()->create([
            'status' => UserStatus::Active,
        ]);

        $accessToken = $user->createToken('test-api-token');

        $this->assertNotEmpty($accessToken->plainTextToken);

        $this->assertDatabaseCount(
            'personal_access_tokens',
            1,
        );

        $this->assertDatabaseCount(
            'authentication_sessions',
            0,
        );
    }

    public function test_revoking_api_token_does_not_revoke_web_session(): void
    {
        $user = User::factory()->create([
            'status' => UserStatus::Active,
        ]);

        $session = AuthenticationSession::query()->create([
            'user_id' => $user->getKey(),
            'session_id' => 'sanctum-test-session',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'browser' => 'Test Browser',
            'device' => 'Test Device',
            'authenticated_at' => now(),
            'last_activity_at' => now(),
            'revoked_at' => null,
            'revocation_reason' => null,
        ]);

        $accessToken = $user->createToken('test-api-token');

        $accessToken->accessToken->delete();

        $session->refresh();

        $this->assertNull($session->revoked_at);
        $this->assertNull($session->revocation_reason);

        $this->assertDatabaseCount(
            'personal_access_tokens',
            0,
        );

        $this->assertDatabaseHas(
            'authentication_sessions',
            [
                'id' => $session->getKey(),
                'revoked_at' => null,
            ],
        );
    }
}