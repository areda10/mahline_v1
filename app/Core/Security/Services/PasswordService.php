<?php

declare(strict_types=1);

namespace App\Core\Security\Services;

use Illuminate\Support\Facades\Hash;

final class PasswordService
{
    /**
     * Hash a plain-text password.
     */
    public function hash(string $password): string
    {
        return Hash::make($password);
    }

    /**
     * Verify a plain-text password against a hash.
     */
    public function verify(string $password, string $hashedPassword): bool
    {
        return Hash::check($password, $hashedPassword);
    }

    /**
     * Determine whether the password hash needs to be rehashed.
     */
    public function needsRehash(string $hashedPassword): bool
    {
        return Hash::needsRehash($hashedPassword);
    }
}