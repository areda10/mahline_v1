<?php

declare(strict_types=1);

namespace App\Domains\Identity\Password\Rules;

use App\Domains\Identity\Users\Models\User;
use InvalidArgumentException;

final class PasswordPolicy
{
    public const MIN_LENGTH = 12;

    public const MAX_LENGTH = 128;

    public static function validate(string $password, ?User $user = null): void
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

        if (preg_match('/\p{Lu}/u', $password) !== 1) {
            throw new InvalidArgumentException(
                'Password must contain at least one uppercase letter.'
            );
        }

        if (preg_match('/\p{Ll}/u', $password) !== 1) {
            throw new InvalidArgumentException(
                'Password must contain at least one lowercase letter.'
            );
        }

        if (preg_match('/\p{N}/u', $password) !== 1) {
            throw new InvalidArgumentException(
                'Password must contain at least one digit.'
            );
        }

        if (preg_match('/[^\p{L}\p{N}\p{Z}\s]/u', $password) !== 1) {
            throw new InvalidArgumentException(
                'Password must contain at least one special character.'
            );
        }

        if ($user !== null) {
            self::rejectPersonalInformation($password, $user);
        }
    }

    public static function validateChange(
        string $currentPassword,
        string $newPassword,
        ?User $user = null,
    ): void {
        if ($currentPassword === $newPassword) {
            throw new InvalidArgumentException(
                'New password must be different from current password.'
            );
        }

        self::validate($newPassword, $user);
    }

    private static function rejectPersonalInformation(
        string $password,
        User $user,
    ): void {
        $normalizedPassword = self::normalizeForComparison($password);

        $candidates = [
            (string) $user->first_name,
            trim((string) $user->first_name.' '.(string) $user->last_name),
        ];

        $emailLocalPart = strstr((string) $user->email, '@', true);

        if (is_string($emailLocalPart)) {
            if (mb_strlen(self::normalizeForComparison($emailLocalPart)) >= 4) {
                $candidates[] = $emailLocalPart;
            }

            foreach (preg_split('/[^\\p{L}\\p{N}]+/u', $emailLocalPart) ?: [] as $part) {
                if (mb_strlen(self::normalizeForComparison($part)) >= 4) {
                    $candidates[] = $part;
                }
            }
        }

        $identifier = self::normalizeForComparison((string) $user->getKey());

        if ($identifier !== '' && str_contains($normalizedPassword, $identifier)) {
            throw new InvalidArgumentException(
                'Password must not contain recognizable personal information.'
            );
        }

        foreach ($candidates as $candidate) {
            $normalizedCandidate = self::normalizeForComparison($candidate);

            if (mb_strlen($normalizedCandidate) < 3) {
                continue;
            }

            if (str_contains($normalizedPassword, $normalizedCandidate)) {
                throw new InvalidArgumentException(
                    'Password must not contain recognizable personal information.'
                );
            }
        }
    }

    private static function normalizeForComparison(string $value): string
    {
        $normalized = mb_strtolower($value);

        return preg_replace('/[^\p{L}\p{N}]/u', '', $normalized) ?? '';
    }
}
