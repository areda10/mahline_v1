<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Authentication\Http\Api;

use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SanctumAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_protected_api(): void
    {
        $response = $this->getJson('/api/test-auth');

        $response->assertUnauthorized();
    }

    public function test_invalid_token_cannot_access_protected_api(): void
    {
        $response = $this
            ->withHeader('Authorization', 'Bearer invalid-token')
            ->getJson('/api/test-auth');

        $response->assertUnauthorized();
    }

    public function test_valid_token_authenticates_user(): void
    {
        $user = User::factory()->create([
            'status' => UserStatus::Active,
        ]);

        $accessToken = $user->createToken(
            'api-test',
            ['*'],
        );

        $response = $this
            ->withHeader(
                'Authorization',
                'Bearer '.$accessToken->plainTextToken,
            )
            ->getJson('/api/test-auth');

        $response
            ->assertOk()
            ->assertJson([
                'authenticated' => true,
                'user_id' => $user->getKey(),
            ]);
    }

    public function test_valid_token_authenticates_the_correct_user(): void
    {
        $userA = User::factory()->create([
            'status' => UserStatus::Active,
        ]);

        $userB = User::factory()->create([
            'status' => UserStatus::Active,
        ]);

        $accessToken = $userA->createToken('api-test');

        $response = $this
            ->withHeader(
                'Authorization',
                'Bearer '.$accessToken->plainTextToken,
            )
            ->getJson('/api/test-auth');

        $response
            ->assertOk()
            ->assertJsonPath('user_id', $userA->getKey())
            ->assertJsonMissing([
                'user_id' => $userB->getKey(),
            ]);
    }

    public function test_revoked_token_cannot_access_protected_api(): void
    {
        $user = User::factory()->create([
            'status' => UserStatus::Active,
        ]);

        $accessToken = $user->createToken(
            'api-revocation-test',
            ['*'],
        );

        $plainTextToken = $accessToken->plainTextToken;

        $accessToken->accessToken->delete();

        $response = $this
            ->withHeader(
                'Authorization',
                'Bearer '.$plainTextToken,
            )
            ->getJson('/api/test-auth');

        $response->assertUnauthorized();
}
}