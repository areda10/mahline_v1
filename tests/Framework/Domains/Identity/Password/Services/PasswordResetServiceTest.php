<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Password\Services;

use App\Domains\Identity\Authentication\Models\AuthenticationSession;
use App\Domains\Identity\Password\Services\PasswordResetService;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PasswordResetServiceTest extends TestCase
{
    use RefreshDatabase;

    private PasswordResetService $passwordResetService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->passwordResetService = app(
            PasswordResetService::class
        );
    }

    public function test_reset_token_can_be_created_for_user(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
        ]);

        $token = $this->passwordResetService->createToken(
            user: $user,
        );

        $this->assertNotEmpty($token);

        $this->assertDatabaseCount(
            'password_reset_tokens',
            1,
        );
    }

    public function test_plain_reset_token_is_not_stored(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
        ]);

        $token = $this->passwordResetService->createToken(
            user: $user,
        );

        $record = \DB::table('password_reset_tokens')
            ->where('user_id', $user->getKey())
            ->first();

        $this->assertNotNull($record);

        $this->assertNotSame(
            $token,
            $record->token_hash,
        );
    }

    public function test_valid_reset_token_is_accepted(): void
    {
        $user = User::factory()->create();

        $token = $this->passwordResetService->createToken(
            user: $user,
        );

        $resolvedUser = $this->passwordResetService->validateToken(
            token: $token,
        );

        $this->assertTrue(
            $resolvedUser->is($user)
        );
    }

    public function test_invalid_reset_token_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Invalid password reset token.'
        );

        $this->passwordResetService->validateToken(
            token: 'invalid-token',
        );
    }

    public function test_expired_reset_token_is_rejected(): void
    {
        $user = User::factory()->create();

        $token = $this->passwordResetService->createToken(
            user: $user,
        );

        \DB::table('password_reset_tokens')
            ->where('user_id', $user->getKey())
            ->update([
                'expires_at' => now()->subMinute(),
            ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Password reset token has expired.'
        );

        $this->passwordResetService->validateToken(
            token: $token,
        );
    }

    public function test_used_reset_token_is_rejected(): void
    {
        $user = User::factory()->create();

        $token = $this->passwordResetService->createToken(
            user: $user,
        );

        \DB::table('password_reset_tokens')
            ->where('user_id', $user->getKey())
            ->update([
                'used_at' => now(),
            ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Password reset token has already been used.'
        );

        $this->passwordResetService->validateToken(
            token: $token,
        );
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $user = User::factory()->create([
            'password' => 'MahlinePassword12',
        ]);

        $token = $this->passwordResetService->createToken(
            user: $user,
        );

        $this->passwordResetService->resetPassword(
            token: $token,
            newPassword: 'MahlinePassword13',
        );

        $user->refresh();

        $this->assertTrue(
            \Hash::check(
                'MahlinePassword13',
                (string) $user->password,
            )
        );

        $this->assertFalse(
            \Hash::check(
                'MahlinePassword12',
                (string) $user->password,
            )
        );
    }

    public function test_reset_token_is_marked_as_used_after_password_reset(): void
    {
        $user = User::factory()->create([
            'password' => 'MahlinePassword12',
        ]);

        $token = $this->passwordResetService->createToken(
            user: $user,
        );

        $this->passwordResetService->resetPassword(
            token: $token,
            newPassword: 'MahlinePassword13',
        );

        $record = \DB::table('password_reset_tokens')
            ->where('user_id', $user->getKey())
            ->first();

        $this->assertNotNull($record);
        $this->assertNotNull($record->used_at);
    }

    public function test_password_reset_rejects_invalid_password(): void
    {
        $user = User::factory()->create([
            'password' => 'MahlinePassword12',
        ]);

        $token = $this->passwordResetService->createToken(
            user: $user,
        );

        $this->expectException(\InvalidArgumentException::class);

        $this->passwordResetService->resetPassword(
            token: $token,
            newPassword: 'short',
        );
    }

    public function test_password_reset_rejects_same_password(): void
    {
        $user = User::factory()->create([
            'password' => 'MahlinePassword12',
        ]);

        $token = $this->passwordResetService->createToken(
            user: $user,
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'New password must be different from current password.'
        );

        $this->passwordResetService->resetPassword(
            token: $token,
            newPassword: 'MahlinePassword12',
        );
    }

    public function test_password_reset_revokes_all_active_sessions(): void
    {
        $user = User::factory()->create([
            'password' => 'MahlinePassword12',
        ]);

        AuthenticationSession::query()
            ->create([
                'user_id' => $user->getKey(),
                'session_id' => 'reset-session-1',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'PHPUnit',
                'browser' => 'Test Browser',
                'device' => 'Test Device',
                'authenticated_at' => now(),
                'last_activity_at' => now(),
                'revoked_at' => null,
                'revocation_reason' => null,
            ]);

        AuthenticationSession::query()
            ->create([
                'user_id' => $user->getKey(),
                'session_id' => 'reset-session-2',
                'ip_address' => '127.0.0.2',
                'user_agent' => 'PHPUnit',
                'browser' => 'Another Browser',
                'device' => 'Another Device',
                'authenticated_at' => now(),
                'last_activity_at' => now(),
                'revoked_at' => null,
                'revocation_reason' => null,
            ]);

        $token = $this->passwordResetService->createToken(
            user: $user,
        );

        $this->passwordResetService->resetPassword(
            token: $token,
            newPassword: 'MahlinePassword13',
        );

        $sessions = AuthenticationSession::query()
            ->where('user_id', $user->getKey())
            ->get();

        $this->assertCount(2, $sessions);

        foreach ($sessions as $session) {
            $this->assertNotNull($session->revoked_at);

            $this->assertSame(
                'password_reset',
                $session->revocation_reason,
            );
        }
    }
}