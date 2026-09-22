<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\Services;

use App\Core\Foundation\Services\BaseService;
use Illuminate\Support\Facades\RateLimiter;

final class AuthenticationSecurityService extends BaseService
{
    private const MAX_FAILED_ATTEMPTS = 5;

    private const LOCKOUT_SECONDS = 900;

    public function recordFailedAttempt(
        string $email,
    ): void {
        RateLimiter::hit(
            $this->key($email),
            self::LOCKOUT_SECONDS,
        );
    }

    public function isLocked(
        string $email,
    ): bool {
        return RateLimiter::tooManyAttempts(
            $this->key($email),
            self::MAX_FAILED_ATTEMPTS,
        );
    }

    private function key(string $email): string
    {
        return 'authentication:failed:'.mb_strtolower(
            trim($email),
        );
    }

    public function clearFailedAttempts(
        string $email,
    ): void {
        RateLimiter::clear(
            $this->key($email),
        );
    }

    public function remainingAttempts(
        string $email,
    ): int {
        return max(
            0,
            self::MAX_FAILED_ATTEMPTS
                - RateLimiter::attempts($this->key($email)),
        );
    }

    public function recordFailedAttemptByIp(
        string $ipAddress,
    ): void {
        RateLimiter::hit(
            $this->ipKey($ipAddress),
            self::LOCKOUT_SECONDS,
        );
    }

    public function isIpLocked(
        string $ipAddress,
    ): bool {
        return RateLimiter::tooManyAttempts(
            $this->ipKey($ipAddress),
            self::MAX_FAILED_ATTEMPTS,
        );
    }

    private function ipKey(string $ipAddress): string
    {
        return 'authentication:failed:ip:'.$ipAddress;
    }

    public function remainingIpAttempts(
        string $ipAddress,
    ): int {
        return max(
            0,
            self::MAX_FAILED_ATTEMPTS
                - RateLimiter::attempts($this->ipKey($ipAddress)),
        );
    }

    public function clearFailedIpAttempts(
        string $ipAddress,
    ): void {
        RateLimiter::clear(
            $this->ipKey($ipAddress),
        );
    }

    public function lockoutSecondsRemaining(
        string $email,
    ): int {
        return RateLimiter::availableIn(
            $this->key($email),
        );
    }

    public function ipLockoutSecondsRemaining(
        string $ipAddress,
    ): int {
        return RateLimiter::availableIn(
            $this->ipKey($ipAddress),
        );
    }
}