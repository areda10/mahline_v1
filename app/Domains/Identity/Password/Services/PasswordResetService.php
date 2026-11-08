<?php

declare(strict_types=1);

namespace App\Domains\Identity\Password\Services;

use App\Core\Foundation\Services\BaseService;
use App\Domains\Audit\Services\AuditService;
use App\Domains\Identity\Authentication\Services\SessionService;
use App\Domains\Identity\Password\Exceptions\InvalidPasswordResetTokenException;
use App\Domains\Identity\Password\Notifications\PasswordResetLinkNotification;
use App\Domains\Identity\Password\Rules\PasswordPolicy;
use App\Domains\Identity\Users\Models\User;
use App\Shared\DTOs\AuditEntryDTO;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class PasswordResetService extends BaseService
{
    private const TOKEN_BYTES = 32;

    public function __construct(
        private readonly SessionService $sessionService,
        private readonly AuditService $audit,
        private readonly PasswordHistoryService $passwordHistory,
    ) {
    }

    public function createToken(User $user): string
    {
        $token = bin2hex(random_bytes(self::TOKEN_BYTES));

        DB::transaction(function () use ($user, $token): void {
            $lockedUser = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedUser->status->value !== 'active') {
                throw new InvalidPasswordResetTokenException('This account cannot reset its password.');
            }

            DB::table('password_reset_tokens')
                ->where('user_id', $lockedUser->getKey())
                ->whereNull('used_at')
                ->update(['used_at' => now()]);

            DB::table('password_reset_tokens')->insert([
            'id' => (string) Str::ulid(),
            'user_id' => $lockedUser->getKey(),
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes(30),
            'used_at' => null,
            'created_at' => now(),
            ]);
        });

        $resetBaseUrl = rtrim(
            (string) config('app.password_reset_url', config('app.url')),
            '/',
        );

        $resetUrl = $resetBaseUrl . '/password/reset?' . http_build_query([
            'token' => $token,
            'email' => $user->email,
        ]);

        Notification::route('mail', $user->email)->notify(
            new PasswordResetLinkNotification($resetUrl),
        );

        return $token;
    }

    public function validateToken(string $token): User
    {
        $tokenHash = hash('sha256', $token);

        $record = DB::table('password_reset_tokens')
            ->where('token_hash', $tokenHash)
            ->first();

        if ($record === null) {
            throw new InvalidPasswordResetTokenException('Invalid password reset token.');
        }

        if ($record->used_at !== null) {
            throw new InvalidPasswordResetTokenException('Password reset token has already been used.');
        }

        if (now()->greaterThanOrEqualTo(\Illuminate\Support\Carbon::parse($record->expires_at))) {
            throw new InvalidPasswordResetTokenException('Password reset token has expired.');
        }

        $user = User::query()->find($record->user_id);

        if ($user === null || $user->status->value !== 'active') {
            throw new InvalidPasswordResetTokenException('Invalid password reset token.');
        }

        return $user;
    }

    /**
     * Consume the token, change the password, revoke all active
     * sessions and append the audit event atomically.
     */
    public function resetPassword(string $token, string $newPassword): void
    {
        DB::transaction(function () use ($token, $newPassword): void {
            $tokenHash = hash('sha256', $token);

            $record = DB::table('password_reset_tokens')
                ->where('token_hash', $tokenHash)
                ->lockForUpdate()
                ->first();

            if ($record === null) {
                throw new InvalidPasswordResetTokenException('Invalid password reset token.');
            }

            if ($record->used_at !== null) {
                throw new InvalidPasswordResetTokenException('Password reset token has already been used.');
            }

            if (now()->greaterThanOrEqualTo(\Illuminate\Support\Carbon::parse($record->expires_at))) {
                throw new InvalidPasswordResetTokenException('Password reset token has expired.');
            }

            $user = User::query()
                ->whereKey($record->user_id)
                ->lockForUpdate()
                ->first();

            if ($user === null || $user->status->value !== 'active') {
                throw new InvalidPasswordResetTokenException('Invalid password reset token.');
            }

            PasswordPolicy::validate($newPassword, $user);
            $this->passwordHistory->assertNotReused($user, $newPassword);
            $this->passwordHistory->archiveCurrentPassword($user);

            if (Hash::check($newPassword, (string) $user->password)) {
                throw new InvalidArgumentException(
                    'New password must be different from current password.',
                );
            }

            $user->password = $newPassword;
            $user->save();

            DB::table('password_reset_tokens')
                ->where('token_hash', $tokenHash)
                ->whereNull('used_at')
                ->update(['used_at' => now()]);

            $this->sessionService->revokeForUser(
                user: $user,
                reason: 'password_reset',
            );
            $user->tokens()->delete();

            $this->audit->record(new AuditEntryDTO(
                eventKey: 'identity.password.reset',
                action: 'reset',
                cooperativeId: $user->cooperative_id
                    ? (string) $user->cooperative_id
                    : null,
                subjectType: User::class,
                subjectId: (string) $user->getKey(),
                metadata: ['reason' => 'password_reset_token'],
            ));
        });
    }
}
