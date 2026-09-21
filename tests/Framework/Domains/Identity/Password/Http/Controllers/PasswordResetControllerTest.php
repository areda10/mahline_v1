<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Password\Http\Controllers;

use App\Domains\Identity\Password\Services\PasswordResetService;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class PasswordResetControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_creates_reset_token_for_existing_user(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
        ]);

        $response = $this->postJson(
            '/api/password/forgot',
            [
                'email' => 'user@example.com',
            ],
        );

        $response->assertSuccessful();

        $this->assertDatabaseHas(
            'password_reset_tokens',
            [
                'user_id' => $user->getKey(),
            ],
        );

        $token = DB::table('password_reset_tokens')
            ->where('user_id', $user->getKey())
            ->first();

        $this->assertNotNull($token);
        $this->assertNotEmpty($token->token_hash);
    }

    public function test_forgot_password_does_not_reveal_non_existing_user(): void
    {
        $response = $this->postJson(
            '/api/password/forgot',
            [
                'email' => 'unknown@example.com',
            ],
        );

        $response->assertSuccessful();

        $response->assertJson([
            'message' => 'If the email address exists, a password reset link has been sent.',
        ]);

        $this->assertDatabaseCount(
            'password_reset_tokens',
            0,
        );
    }

    public function test_forgot_password_rejects_invalid_email(): void
    {
        $response = $this->postJson(
            '/api/password/forgot',
            [
                'email' => 'invalid-email',
            ],
        );

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'email',
        ]);

        $this->assertDatabaseCount(
            'password_reset_tokens',
            0,
        );
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => 'OldPassword123',
        ]);

        /** @var \App\Domains\Identity\Password\Services\PasswordResetService $service */
        $service = app(
            PasswordResetService::class,
        );

        $token = $service->createToken($user);

        $response = $this->postJson(
            '/api/password/reset',
            [
                'token' => $token,
                'password' => 'NewPassword123',
                'password_confirmation' => 'NewPassword123',
            ],
        );

        $response->assertSuccessful();

        $user->refresh();

        $this->assertTrue(
            \Illuminate\Support\Facades\Hash::check(
                'NewPassword123',
                (string) $user->password,
            ),
        );
    }

    public function test_password_reset_rejects_invalid_token_with_unprocessable_entity(): void
    {
        $response = $this->postJson(
            '/api/password/reset',
            [
                'token' => 'invalid-reset-token',
                'password' => 'NewPassword123',
                'password_confirmation' => 'NewPassword123',
            ],
        );

        $response->assertUnprocessable();
    }

    public function test_password_reset_rejects_expired_token(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => 'OldPassword123',
        ]);

        $service = app(
            PasswordResetService::class,
        );

        $token = $service->createToken($user);

        \Illuminate\Support\Facades\DB::table('password_reset_tokens')
            ->where('user_id', $user->getKey())
            ->update([
                'expires_at' => now()->subMinute(),
            ]);

        $response = $this->postJson(
            '/api/password/reset',
            [
                'token' => $token,
                'password' => 'NewPassword123',
                'password_confirmation' => 'NewPassword123',
            ],
        );

        $response->assertUnprocessable();
    }

    public function test_password_reset_rejects_invalid_password(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => 'OldPassword123',
        ]);

        $service = app(
            PasswordResetService::class,
        );

        $token = $service->createToken($user);

        $response = $this->postJson(
            '/api/password/reset',
            [
                'token' => $token,
                'password' => 'weak',
                'password_confirmation' => 'weak',
            ],
        );

        $response->assertUnprocessable();
    }

    public function test_password_reset_rejects_mismatched_password_confirmation(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => 'OldPassword123',
        ]);

        $service = app(
            PasswordResetService::class,
        );

        $token = $service->createToken($user);

        $response = $this->postJson(
            '/api/password/reset',
            [
                'token' => $token,
                'password' => 'NewPassword123',
                'password_confirmation' => 'DifferentPassword123',
            ],
        );

        $response->assertUnprocessable();
    }

    public function test_password_reset_rejects_missing_token(): void
    {
        $response = $this->postJson(
            '/api/password/reset',
            [
                'password' => 'NewPassword123',
                'password_confirmation' => 'NewPassword123',
            ],
        );

        $response->assertUnprocessable();
    }
}