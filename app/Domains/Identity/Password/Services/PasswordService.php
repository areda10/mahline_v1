<?php

declare(strict_types=1);

namespace App\Domains\Identity\Password\Services;

use App\Core\Foundation\Services\BaseService;
use App\Domains\Audit\Services\AuditService;
use App\Domains\Identity\Authentication\Services\SessionService;
use App\Domains\Identity\Password\Rules\PasswordPolicy;
use App\Domains\Identity\Users\Models\User;
use App\Shared\DTOs\AuditEntryDTO;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

final class PasswordService extends BaseService
{
    public function __construct(
        private readonly SessionService $sessionService,
        private readonly AuditService $audit,
        private readonly PasswordHistoryService $passwordHistory,
    ) {
    }

    public function verifyCurrentPassword(User $user, string $password): bool
    {
        return Hash::check($password, (string) $user->password);
    }

    /**
     * Update the password, revoke all active sessions and append
     * one audit event in the same database transaction.
     */
    public function changePassword(
        User $user,
        string $currentPassword,
        string $newPassword,
    ): void {
        DB::transaction(function () use ($user, $currentPassword, $newPassword): void {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $this->verifyCurrentPassword($lockedUser, $currentPassword)) {
                throw new RuntimeException('Current password is invalid.');
            }

            PasswordPolicy::validateChange(
                currentPassword: $currentPassword,
                newPassword: $newPassword,
                user: $lockedUser,
            );

            $this->passwordHistory->assertNotReused($lockedUser, $newPassword);
            $this->passwordHistory->archiveCurrentPassword($lockedUser);

            $lockedUser->password = $newPassword;
            $lockedUser->save();

            $this->sessionService->revokeForUser(
                user: $lockedUser,
                reason: 'password_changed',
            );
            $lockedUser->tokens()->delete();

            $this->audit->record(new AuditEntryDTO(
                eventKey: 'identity.password.changed',
                action: 'changed',
                actorType: User::class,
                actorId: (string) $lockedUser->getKey(),
                cooperativeId: $lockedUser->cooperative_id
                    ? (string) $lockedUser->cooperative_id
                    : null,
                subjectType: User::class,
                subjectId: (string) $lockedUser->getKey(),
                metadata: ['reason' => 'user_initiated'],
            ));
        });
    }
}
