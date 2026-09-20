<?php

declare(strict_types=1);

namespace App\Domains\Identity\Password\Services;

use App\Core\Foundation\Services\BaseService;
use App\Domains\Identity\Authentication\Services\SessionService;
use App\Domains\Identity\Password\Rules\PasswordPolicy;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

final class PasswordResetService extends BaseService
{
    private const TOKEN_BYTES = 32;

    public function __construct(
        private readonly SessionService $sessionService,
    ) {
    }

    public function createToken(User $user): string
    {
        $token = bin2hex(
            random_bytes(self::TOKEN_BYTES)
        );

        DB::table('password_reset_tokens')->insert([
            'id' => (string) Str::ulid(),
            'user_id' => $user->getKey(),
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes(60),
            'used_at' => null,
            'created_at' => now(),
        ]);

        return $token;
    }

    public function validateToken(string $token): User
    {
        $tokenHash = hash('sha256', $token);

        $record = DB::table('password_reset_tokens')
            ->where('token_hash', $tokenHash)
            ->first();

        if ($record === null) {
            throw new RuntimeException(
                'Invalid password reset token.'
            );
        }

        if ($record->used_at !== null) {
            throw new RuntimeException(
                'Password reset token has already been used.'
            );
        }

        if (now()->greaterThan(
            \Illuminate\Support\Carbon::parse($record->expires_at)
        )) {
            throw new RuntimeException(
                'Password reset token has expired.'
            );
        }

        $user = User::query()->find($record->user_id);

        if ($user === null) {
            throw new RuntimeException(
                'Invalid password reset token.'
            );
        }

        return $user;
    }

    public function resetPassword(
        string $token,
        string $newPassword,
    ): void 
    {
        $user = $this->validateToken(
            token: $token,
        );

        PasswordPolicy::validate(
            $newPassword,
        );

        if (Hash::check(
            $newPassword,
            (string) $user->password,
        )) {
            throw new InvalidArgumentException(
                'New password must be different from current password.'
            );
        }

        $user->password = $newPassword;
        $user->save();

        DB::table('password_reset_tokens')
            ->where(
                'token_hash',
                hash('sha256', $token),
            )
            ->update([
                'used_at' => now(),
            ]);

        $this->sessionService->revokeForUser(
            user: $user,
            reason: 'password_reset',
        );
    }

    public function test_password_reset_revokes_all_active_sessions(): void
    {
        $user = User::factory()->create([
            'password' => 'MahlinePassword12',
        ]);

        \App\Domains\Identity\Authentication\Models\AuthenticationSession::query()
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

        \App\Domains\Identity\Authentication\Models\AuthenticationSession::query()
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

        $sessions = \App\Domains\Identity\Authentication\Models\AuthenticationSession::query()
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