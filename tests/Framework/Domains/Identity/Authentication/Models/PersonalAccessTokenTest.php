<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Authentication\Models;

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
}