<?php

declare(strict_types=1);

namespace App\Domains\Identity\Password\Rules;

use InvalidArgumentException;

final class PasswordPolicy
{
    public const MIN_LENGTH = 12;

    public const MAX_LENGTH = 128;

    public static function validate(string $password): void
    {
        $length = mb_strlen($password);

        if ($length < self::MIN_LENGTH) {
            throw new InvalidArgumentException(
                'Password must contain at least 12 characters.'
            );
        }

        if ($length > self::MAX_LENGTH) {
            throw new InvalidArgumentException(
                'Password must not exceed 128 characters.'
            );
        }

        if (preg_match('/\s/u', $password) === 1) {
            throw new InvalidArgumentException(
                'Password must not contain spaces.'
            );
        }

        if (preg_match('/[A-Z]/', $password) !== 1) {
            throw new InvalidArgumentException(
                'Password must contain at least one uppercase letter.'
            );
        }

        if (preg_match('/[a-z]/', $password) !== 1) {
            throw new InvalidArgumentException(
                'Password must contain at least one lowercase letter.'
            );
        }

        if (preg_match('/\d/', $password) !== 1) {
            throw new InvalidArgumentException(
                'Password must contain at least one digit.'
            );
        }
    }

    public static function validateChange(
        string $currentPassword,
        string $newPassword,
    ): void {
        if ($currentPassword === $newPassword) {
            throw new InvalidArgumentException(
                'New password must be different from current password.'
            );
        }

        self::validate($newPassword);
    }
}