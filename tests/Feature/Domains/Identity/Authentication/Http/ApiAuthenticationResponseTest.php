<?php

declare(strict_types=1);

namespace Tests\\Feature\\Domains\\Identity\\Authentication\\Http;

use App\\Domains\\Identity\\Enums\\UserStatus;
use App\\Domains\\Identity\\Users\\Models\\User;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Tests\\TestCase;

final class ApiAuthenticationResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_protected_route_returns_json_401_without_a_token(): void
    {
        $this->get('/api/test-auth')
            ->assertUnauthorized()
            ->assertExactJson([
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_api_protected_route_returns_200_for_a_valid_sanctum_token(): void
    {
        $user = User::factory()->create([
            'status' => UserStatus::Active,
            'must_change_password' => false,
        ]);
        $token = $user->createToken('api-response-test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/test-auth')
            ->assertOk()
            ->assertExactJson([
                'authenticated' => true,
                'user_id' => $user->getKey(),
            ]);
    }

    public function test_api_protected_route_returns_403_when_password_change_is_required(): void
    {
        $user = User::factory()->create([
            'status' => UserStatus::Active,
            'must_change_password' => true,
        ]);
        $token = $user->createToken('forced-password-change-test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/test-auth')
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'A password change is required before accessing this resource.',
            ]);
    }

    public function test_api_password_forgot_validation_returns_422_json(): void
    {
        $this->postJson('/api/password/forgot', [
            'email' => 'not-an-email',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The given data was invalid.')
            ->assertJsonPath('errors.email.0', 'The email address is invalid.');
    }

    public function test_api_password_forgot_does_not_disclose_unknown_accounts(): void
    {
        $this->postJson('/api/password/forgot', [
            'email' => 'unknown-reset-target@example.com',
        ])
            ->assertOk()
            ->assertExactJson([
                'message' => 'If the email address exists, a password reset link has been sent.',
            ]);
    }

    public function test_api_password_reset_validation_returns_422_json(): void
    {
        $this->postJson('/api/password/reset', [])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The given data was invalid.')
            ->assertJsonStructure([
                'errors' => ['token', 'password', 'password_confirmation'],
            ]);
    }
}
