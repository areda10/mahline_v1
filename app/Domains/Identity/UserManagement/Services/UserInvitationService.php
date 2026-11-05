<?php

declare(strict_types=1);

namespace App\Domains\Identity\UserManagement\Services;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Password\Rules\PasswordPolicy;
use App\Domains\Identity\UserManagement\Exceptions\UserInvitationDeliveryException;
use App\Domains\Identity\UserManagement\Models\UserInvitation;
use App\Domains\Identity\UserManagement\Notifications\UserInvitationNotification;
use App\Domains\Identity\Users\Models\User;
use App\Shared\DTOs\AuditEntryDTO;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

final class UserInvitationService
{
    private const TOKEN_BYTES = 32;

    private const EXPIRATION_MINUTES = 60;

    public function __construct(
        private readonly AuditService $audit,
    ) {
    }

    /**
     * Create and send a new invitation to an active or pending, unverified user.
     */
    public function sendInvitation(User $user): void
    {
        $token = bin2hex(random_bytes(self::TOKEN_BYTES));

        [$invitation, $recipientEmail] = DB::transaction(function () use (
            $user,
            $token,
        ): array {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->first();

            if ($lockedUser === null) {
                throw new DomainException(
                    'The invited user does not exist or is deleted.',
                );
            }

            if (
                ! in_array($lockedUser->status, [UserStatus::Active, UserStatus::Pending], true)
                || $lockedUser->email_verified_at !== null
            ) {
                throw new DomainException(
                    'Only active or pending, unverified users can receive an invitation.',
                );
            }

            UserInvitation::query()
                ->where('user_id', $lockedUser->getKey())
                ->whereNull('used_at')
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => now(),
                ]);

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
                eventKey: 'identity.user.invitation_created',
                action: 'invitation_created',
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

            return [
                $invitation,
                $lockedUser->email,
            ];
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

        try {
            Notification::route('mail', $recipientEmail)->notify(
                new UserInvitationNotification($invitationUrl),
            );
        } catch (Throwable $exception) {
            DB::transaction(function () use ($invitation): void {
                $lockedUser = User::query()
                    ->whereKey($invitation->user_id)
                    ->lockForUpdate()
                    ->first();

                if ($lockedUser === null) {
                    return;
                }

                $lockedInvitation = UserInvitation::query()
                    ->whereKey($invitation->getKey())
                    ->lockForUpdate()
                    ->first();

                if (
                    $lockedInvitation !== null
                    && $lockedInvitation->used_at === null
                    && $lockedInvitation->revoked_at === null
                ) {
                    $lockedInvitation->revoked_at = now();
                    $lockedInvitation->save();
                }
            });

            throw new UserInvitationDeliveryException(
                'The user was created, but the invitation email could not be delivered.',
                $exception,
            );
        }

        $this->audit->record(new AuditEntryDTO(
            eventKey: 'identity.user.invitation_sent',
            action: 'invitation_sent',
            subjectType: User::class,
            subjectId: (string) $invitation->user_id,
            metadata: [
                'invitation_id' => (string) $invitation->getKey(),
                'expires_at' => $invitation->expires_at->toISOString(),
            ],
        ));
    }

    /**
     * Validate an invitation without consuming it.
     *
     * The invitation is checked again transactionally during acceptance.
     */
    public function validateInvitation(string $token): void
    {
        if ($token === '') {
            throw new InvalidArgumentException(
                'An invitation token is required.',
            );
        }

        $tokenHash = hash('sha256', $token);

        $invitation = UserInvitation::query()
            ->where('token_hash', $tokenHash)
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
            ->first();

        if ($user === null) {
            throw new DomainException(
                'The invited user does not exist or is deleted.',
            );
        }

        if (
            ! in_array($user->status, [UserStatus::Active, UserStatus::Pending], true)
            || $user->email_verified_at !== null
        ) {
            throw new DomainException(
                'Only active or pending, unverified users can accept an invitation.',
            );
        }
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
            /*
             * First lookup only identifies the user.
             * No lock is acquired here.
             */
            $invitationReference = UserInvitation::query()
                ->where('token_hash', $tokenHash)
                ->first();

            if ($invitationReference === null) {
                throw new DomainException(
                    'The invitation token is invalid.',
                );
            }

            /*
             * Lock order is always:
             * User -> UserInvitation
             */
            $user = User::query()
                ->whereKey($invitationReference->user_id)
                ->lockForUpdate()
                ->first();

            if ($user === null) {
                throw new DomainException(
                    'The invited user does not exist or is deleted.',
                );
            }

            $invitation = UserInvitation::query()
                ->whereKey($invitationReference->getKey())
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->first();

            if ($invitation === null) {
                throw new DomainException(
                    'The invitation token is invalid.',
                );
            }

            if (
                ! hash_equals(
                    $invitation->token_hash,
                    $tokenHash,
                )
            ) {
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

            if (
                ! in_array($user->status, [UserStatus::Active, UserStatus::Pending], true)
                || $user->email_verified_at !== null
            ) {
                throw new DomainException(
                    'Only active or pending, unverified users can accept an invitation.',
                );
            }

            $user->password = $password;

            if ($user->status === UserStatus::Pending) {
                $user->status = UserStatus::Active;
            }
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
