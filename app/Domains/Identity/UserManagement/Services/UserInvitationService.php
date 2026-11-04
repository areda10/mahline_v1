<?php

declare(strict_types=1);

namespace App\Domains\Identity\UserManagement\Services;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Password\Rules\PasswordPolicy;
use App\Domains\Identity\UserManagement\Models\UserInvitation;
use App\Domains\Identity\UserManagement\Notifications\UserInvitationNotification;
use App\Domains\Identity\Users\Models\User;
use App\Shared\DTOs\AuditEntryDTO;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class UserInvitationService
{
    private const TOKEN_BYTES = 32;

    private const EXPIRATION_MINUTES = 60;

    public function __construct(
        private readonly AuditService $audit,
    ) {
    }

    /**
     * Create and send a new invitation to a pending user.
     */
    public function sendInvitation(User $user): void
    {
        $token = bin2hex(random_bytes(self::TOKEN_BYTES));

        $invitation = DB::transaction(function () use (
            $user,
            $token,
        ): UserInvitation {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->first();

            if ($lockedUser === null) {
                throw new DomainException(
                    'The invited user does not exist or is deleted.',
                );
            }

            if ($lockedUser->status !== UserStatus::Pending) {
                throw new DomainException(
                    'Only pending users can receive an invitation.',
                );
            }

            // Revoke all previous invitations that are still usable.
            UserInvitation::query()
                ->where('user_id', $lockedUser->getKey())
                ->whereNull('used_at')
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            $invitation = UserInvitation::query()->create([
                'id' => (string) Str::ulid(),
                'user_id' => $lockedUser->getKey(),
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addMinutes(
                    self::EXPIRATION_MINUTES,
                ),
                'created_at' => now(),
            ]);

            $this->audit->record(new AuditEntryDTO(
                eventKey: 'identity.user.invitation_sent',
                action: 'invitation_sent',
                cooperativeId: $lockedUser->cooperative_id
                    ? (string) $lockedUser->cooperative_id
                    : null,
                subjectType: User::class,
                subjectId: (string) $lockedUser->getKey(),
                metadata: [
                    'invitation_id' => (string) $invitation->getKey(),
                    'expires_at' => $invitation->expires_at->toISOString(),
                ],
            ));

            return $invitation;
        });

        $invitationUrl = rtrim(
            (string) config(
                'app.user_invitation_url',
                config('app.url'),
            ),
            '/',
        ).'/user/invitation/accept?'.http_build_query([
            'token' => $token,
        ]);

        Notification::route('mail', $user->email)->notify(
            new UserInvitationNotification($invitationUrl),
        );
    }

    /**
     * Accept an invitation and activate its pending user atomically.
     */
    public function acceptInvitation(
        string $token,
        string $password,
    ): User {
        if ($token === '') {
            throw new InvalidArgumentException(
                'An invitation token is required.',
            );
        }

        PasswordPolicy::validate($password);

        $tokenHash = hash('sha256', $token);

        return DB::transaction(function () use (
            $tokenHash,
            $password,
        ): User {
            $invitation = UserInvitation::query()
                ->where('token_hash', $tokenHash)
                ->lockForUpdate()
                ->first();

            if ($invitation === null) {
                throw new DomainException(
                    'The invitation token is invalid.',
                );
            }

            if (
                $invitation->used_at !== null
                || $invitation->revoked_at !== null
                || now()->greaterThanOrEqualTo($invitation->expires_at)
            ) {
                throw new DomainException(
                    'The invitation is no longer valid.',
                );
            }

            $user = User::query()
                ->whereKey($invitation->user_id)
                ->lockForUpdate()
                ->first();

            if ($user === null) {
                throw new DomainException(
                    'The invited user does not exist or is deleted.',
                );
            }

            if ($user->status !== UserStatus::Pending) {
                throw new DomainException(
                    'Only pending users can accept an invitation.',
                );
            }

            $user->password = $password;
            $user->status = UserStatus::Active;
            $user->email_verified_at = now();
            $user->save();

            $invitation->used_at = now();
            $invitation->save();

            $this->audit->record(new AuditEntryDTO(
                eventKey: 'identity.user.invitation_accepted',
                action: 'invitation_accepted',
                cooperativeId: $user->cooperative_id
                    ? (string) $user->cooperative_id
                    : null,
                subjectType: User::class,
                subjectId: (string) $user->getKey(),
                metadata: [
                    'invitation_id' => (string) $invitation->getKey(),
                    'status' => UserStatus::Active->value,
                ],
            ));

            return $user->fresh();
        });
    }
}
