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
            'password' => 'Secure!Current2026',
        ]);

        $token = $this->passwordResetService->createToken(
            user: $user,
        );

        $this->passwordResetService->resetPassword(
            token: $token,
            newPassword: 'Secure!NewPass2026',
        );

        $user->refresh();

        $this->assertTrue(
            \Hash::check(
                'Secure!NewPass2026',
                (string) $user->password,
            )
        );

        $this->assertFalse(
            \Hash::check(
                'Secure!Current2026',
                (string) $user->password,
            )
        );
    }

    public function test_reset_token_is_marked_as_used_after_password_reset(): void
    {
        $user = User::factory()->create([
            'password' => 'Secure!Current2026',
        ]);

        $token = $this->passwordResetService->createToken(
            user: $user,
        );

        $this->passwordResetService->resetPassword(
            token: $token,
            newPassword: 'Secure!NewPass2026',
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
            'password' => 'Secure!Current2026',
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
            'password' => 'Secure!Current2026',
        ]);

        $token = $this->passwordResetService->createToken(
            user: $user,
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'The new password must not match the current password or any of the five previous passwords.'
        );

        $this->passwordResetService->resetPassword(
            token: $token,
            newPassword: 'Secure!Current2026',
        );
    }

    public function test_password_reset_revokes_all_active_sessions(): void
    {
        $user = User::factory()->create([
            'password' => 'Secure!Current2026',
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
            newPassword: 'Secure!NewPass2026',
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

    public function test_invalid_reset_token_does_not_modify_password(): void
    {
        $user = User::factory()->create([
            'password' => 'Secure!Current2026',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Invalid password reset token.'
        );

        try {
            $this->passwordResetService->resetPassword(
                token: 'invalid-token',
                newPassword: 'Secure!NewPass2026',
            );
        } finally {
            $user->refresh();

            $this->assertTrue(
                \Hash::check(
                    'Secure!Current2026',
                    (string) $user->password,
                )
            );

            $this->assertFalse(
                \Hash::check(
                    'Secure!NewPass2026',
                    (string) $user->password,
                )
            );
        }
    }

    public function test_expired_reset_token_does_not_modify_password(): void
    {
        $user = User::factory()->create([
            'password' => 'Secure!Current2026',
        ]);

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

        try {
            $this->passwordResetService->resetPassword(
                token: $token,
                newPassword: 'Secure!NewPass2026',
            );
        } finally {
            $user->refresh();

            $this->assertTrue(
                \Hash::check(
                    'Secure!Current2026',
                    (string) $user->password,
                )
            );

            $this->assertFalse(
                \Hash::check(
                    'Secure!NewPass2026',
                    (string) $user->password,
                )
            );
        }
    }

    public function test_used_reset_token_does_not_modify_password(): void
    {
        $user = User::factory()->create([
            'password' => 'Secure!Current2026',
        ]);

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

        try {
            $this->passwordResetService->resetPassword(
                token: $token,
                newPassword: 'Secure!NewPass2026',
            );
        } finally {
            $user->refresh();

            $this->assertTrue(
                \Hash::check(
                    'Secure!Current2026',
                    (string) $user->password,
                )
            );

            $this->assertFalse(
                \Hash::check(
                    'Secure!NewPass2026',
                    (string) $user->password,
                )
            );
        }
    }

    public function test_password_reset_is_recorded_in_audit(): void
    {
        $user = User::factory()->create(['password' => 'Secure!Current2026']);
        $token = $this->passwordResetService->createToken($user);

        $this->passwordResetService->resetPassword(
            token: $token,
            newPassword: 'Secure!NewPass2026',
        );

        $this->assertDatabaseHas('audit_events', [
            'event_key' => 'identity.password.reset',
            'action' => 'reset',
            'subject_type' => User::class,
            'subject_id' => $user->getKey(),
        ]);
    }

    public function test_invalid_password_reset_does_not_create_audit_event(): void
    {
        $user = User::factory()->create(['password' => 'Secure!Current2026']);

        try {
            $this->passwordResetService->resetPassword(
                token: 'invalid-token',
                newPassword: 'Secure!NewPass2026',
            );
            $this->fail('Expected invalid reset token exception.');
        } catch (\RuntimeException $exception) {
            $this->assertDatabaseMissing('audit_events', [
                'event_key' => 'identity.password.reset',
                'subject_id' => $user->getKey(),
            ]);
        }
    }


    public function test_reset_audit_is_created_after_token_is_consumed(): void
    {
        $user = User::factory()->create(['password' => 'Secure!Current2026']);
        $token = $this->passwordResetService->createToken($user);

        $this->passwordResetService->resetPassword(
            token: $token,
            newPassword: 'Secure!NewPass2026',
        );

        $record = \DB::table('password_reset_tokens')
            ->where('user_id', $user->getKey())
            ->first();

        $this->assertNotNull($record?->used_at);
        $this->assertDatabaseHas('audit_events', [
            'event_key' => 'identity.password.reset',
            'action' => 'reset',
            'subject_type' => User::class,
            'subject_id' => $user->getKey(),
        ]);
    }

    public function test_reset_audit_never_contains_plain_text_token_or_password(): void
    {
        $user = User::factory()->create(['password' => 'Secure!Current2026']);
        $token = $this->passwordResetService->createToken($user);

        $this->passwordResetService->resetPassword(
            token: $token,
            newPassword: 'Secure!NewPass2026',
        );

        $event = \App\Domains\Audit\Models\AuditEvent::query()
            ->where('event_key', 'identity.password.reset')
            ->firstOrFail();

        $serialized = json_encode([
            'old_values' => $event->old_values,
            'new_values' => $event->new_values,
            'metadata' => $event->metadata,
        ], JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString($token, $serialized);
        $this->assertStringNotContainsString('Secure!Current2026', $serialized);
        $this->assertStringNotContainsString('Secure!NewPass2026', $serialized);
    }

}
