<?php

declare(strict_types=1);

namespace App\Domains\Identity\Password\Services;

use App\Core\Foundation\Services\BaseService;
use App\Domains\Identity\Authentication\Services\SessionService;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

final class PasswordService extends BaseService
{
    public function __construct(
        private readonly SessionService $sessionService,
    ) {
    }

    /**
     * Verify the current password of a user.
     */
    public function verifyCurrentPassword(
        User $user,
        string $password,
    ): bool {
        return Hash::check(
            $password,
            (string) $user->password,
        );
    }

    /**
     * Change the user's password.
     *
     * Security rule:
     *
     * PASSWORD CHANGE
     *      ↓
     * NEW PASSWORD
     *      ↓
     * REVOKE CURRENT SESSION
     */
    public function changePassword(
        User $user,
        string $currentPassword,
        string $newPassword,
    ): void {
        if (! $this->verifyCurrentPassword(
            user: $user,
            password: $currentPassword,
        )) {
            throw new RuntimeException(
                'Current password is invalid.'
            );
        }

        if ($currentPassword === $newPassword) {
            throw new RuntimeException(
                'New password must be different from current password.'
            );
        }

        /*
         * User::casts() contains:
         *
         * 'password' => 'hashed'
         *
         * Therefore assigning the plain password here causes
         * Laravel to hash it automatically.
         */
        $user->password = $newPassword;
        $user->save();

        /*
         * A password change invalidates the current session.
         */
        $this->sessionService->revokeForUser(
            user: $user,
            reason: 'password_changed',
        );
    }
}