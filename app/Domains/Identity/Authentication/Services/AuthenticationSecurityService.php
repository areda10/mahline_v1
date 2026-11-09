<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\Services;

use App\Core\Foundation\Services\BaseService;
use Illuminate\Support\Facades\RateLimiter;

final class AuthenticationSecurityService extends BaseService
{
    private const MAX_ACCOUNT_FAILED_ATTEMPTS = 5;

    private const MAX_IP_FAILED_ATTEMPTS = 30;

    private const MAX_IP_EMAIL_FAILED_ATTEMPTS = 5;

    private const LOCKOUT_SECONDS = 900;

    /**
     * Record one processed authentication failure at every applicable level.
     */
    public function recordFailedAuthentication(
        string $email,
        ?string $ipAddress,
    ): void {
        $this->recordFailedAttempt($email);

        if ($ipAddress === null || $ipAddress === '') {
            return;
        }

        $this->recordFailedAttemptByIp($ipAddress);
        $this->recordFailedAttemptByIpAndEmail($ipAddress, $email);
    }

    public function recordFailedAttempt(string $email): void
    {
        RateLimiter::hit(
            $this->accountKey($email),
            self::LOCKOUT_SECONDS,
        );
    }

    public function isLocked(string $email): bool
    {
        return RateLimiter::tooManyAttempts(
            $this->accountKey($email),
            self::MAX_ACCOUNT_FAILED_ATTEMPTS,
        );
    }

    public function remainingAttempts(string $email): int
    {
        return max(
            0,
            self::MAX_ACCOUNT_FAILED_ATTEMPTS
                - RateLimiter::attempts($this->accountKey($email)),
        );
    }

    public function clearFailedAttempts(string $email): void
    {
        RateLimiter::clear($this->accountKey($email));
    }

    public function lockoutSecondsRemaining(string $email): int
    {
        return RateLimiter::availableIn($this->accountKey($email));
    }

    public function recordFailedAttemptByIp(string $ipAddress): void
    {
        RateLimiter::hit(
            $this->ipKey($ipAddress),
            self::LOCKOUT_SECONDS,
        );
    }

    public function isIpLocked(string $ipAddress): bool
    {
        return RateLimiter::tooManyAttempts(
            $this->ipKey($ipAddress),
            self::MAX_IP_FAILED_ATTEMPTS,
        );
    }

    public function remainingIpAttempts(string $ipAddress): int
    {
        return max(
            0,
            self::MAX_IP_FAILED_ATTEMPTS
                - RateLimiter::attempts($this->ipKey($ipAddress)),
        );
    }

    public function clearFailedIpAttempts(string $ipAddress): void
    {
        RateLimiter::clear($this->ipKey($ipAddress));
    }

    public function ipLockoutSecondsRemaining(string $ipAddress): int
    {
        return RateLimiter::availableIn($this->ipKey($ipAddress));
    }

    public function recordFailedAttemptByIpAndEmail(
        string $ipAddress,
        string $email,
    ): void {
        RateLimiter::hit(
            $this->ipEmailKey($ipAddress, $email),
            self::LOCKOUT_SECONDS,
        );
    }

    public function isIpEmailLocked(
        string $ipAddress,
        string $email,
    ): bool {
        return RateLimiter::tooManyAttempts(
            $this->ipEmailKey($ipAddress, $email),
            self::MAX_IP_EMAIL_FAILED_ATTEMPTS,
        );
    }

    public function remainingIpEmailAttempts(
        string $ipAddress,
        string $email,
    ): int {
        return max(
            0,
            self::MAX_IP_EMAIL_FAILED_ATTEMPTS
                - RateLimiter::attempts(
                    $this->ipEmailKey($ipAddress, $email),
                ),
        );
    }

    public function clearFailedIpEmailAttempts(
        string $ipAddress,
        string $email,
    ): void {
        RateLimiter::clear(
            $this->ipEmailKey($ipAddress, $email),
        );
    }

    public function ipEmailLockoutSecondsRemaining(
        string $ipAddress,
        string $email,
    ): int {
        return RateLimiter::availableIn(
            $this->ipEmailKey($ipAddress, $email),
        );
    }

    private function accountKey(string $email): string
    {
        return 'authentication:failed:account:'.$this->fingerprint(
            mb_strtolower(trim($email)),
        );
    }

    private function ipKey(string $ipAddress): string
    {
        return 'authentication:failed:ip:'.$this->fingerprint(
            trim($ipAddress),
        );
    }

    private function ipEmailKey(string $ipAddress, string $email): string
    {
        return 'authentication:failed:ip-email:'.$this->fingerprint(
            trim($ipAddress)."\0".mb_strtolower(trim($email)),
        );
    }

    /**
     * Keep raw email addresses and IP addresses out of cache key names.
     */
    private function fingerprint(string $value): string
    {
        return hash_hmac(
            'sha256',
            $value,
            (string) config('app.key'),
        );
    }
}
