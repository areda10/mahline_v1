<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\Services;

use App\Core\Foundation\Services\BaseService;
use App\Domains\Identity\Authentication\DTOs\AuthenticationContext;
use App\Domains\Identity\Authentication\Enums\SecurityEventType;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

final class AuthenticationService extends BaseService
{
    public function __construct(
        private readonly LoginHistoryService $loginHistoryService,
        private readonly AuthenticationSecurityService $securityService,
        private readonly SecurityEventService $securityEventService,
    ) {}

    /**
     * Authenticate a user.
     *
     * This service is responsible for authentication and security
     * verification only.
     *
     * Creation of the Web authentication session, browser tracking,
     * and successful login history persistence are intentionally
     * handled by the authentication workflow after Laravel
     * regenerates the session ID.
     */
    public function authenticate(
        AuthenticationContext $context,
    ): User {
        $email = mb_strtolower(trim($context->email));

        /*
         * Find the user by email.
         */
        $user = User::query()
            ->where('email', $email)
            ->first();

        /*
         * IP lock takes precedence over account lock. This is an explicit
         * security decision: when both limits are exhausted, the event
         * recorded in login history must be ip_locked.
         */
        /*
         * Verify IP lock.
         */
        if (
            $context->ipAddress !== null
            && $this->securityService->isIpLocked(
                $context->ipAddress,
            )
        ) {
            $this->loginHistoryService->recordFailure(
                email: $email,
                reason: 'ip_locked',
                user: $user,
                ipAddress: $context->ipAddress,
                userAgent: $context->userAgent,
                browser: $context->browser,
                device: $context->device,
            );

            throw new RuntimeException(
                'IP address is temporarily locked.',
            );
        }

        /*
         * Apply the account-based lock consistently, including email
         * addresses that do not currently belong to a user. This avoids
         * making the lockout behavior depend on account existence.
         */
        if ($this->securityService->isLocked($email)) {
            $this->loginHistoryService->recordFailure(
                email: $email,
                reason: 'account_locked',
                user: $user,
                ipAddress: $context->ipAddress,
                userAgent: $context->userAgent,
                browser: $context->browser,
                device: $context->device,
            );

            throw new RuntimeException(
                'Account is temporarily locked.',
            );
        }

        /*
         * Unknown user.
         *
         * Count the failed attempt against the normalized email key even
         * when no account exists. The public response remains generic.
         */
        if ($user === null) {
            $this->securityService->recordFailedAttempt($email);

            $this->loginHistoryService->recordFailure(
                email: $email,
                reason: 'user_not_found',
                user: null,
                ipAddress: $context->ipAddress,
                userAgent: $context->userAgent,
                browser: $context->browser,
                device: $context->device,
            );

            throw (new ModelNotFoundException)
                ->setModel(
                    User::class,
                    [$context->email],
                );
        }

        /*
         * The account must be active.
         */
        if (! $this->isAccountActive($user)) {
            $this->securityService->recordFailedAttempt($email);

            $this->loginHistoryService->recordFailure(
                email: $email,
                reason: 'account_not_active',
                user: $user,
                ipAddress: $context->ipAddress,
                userAgent: $context->userAgent,
                browser: $context->browser,
                device: $context->device,
            );

            throw new RuntimeException(
                'User account is not active.',
            );
        }

        /*
         * An active account must also have a verified email address.
         */
        if ($user->email_verified_at === null) {
            $this->securityService->recordFailedAttempt($email);

            $this->loginHistoryService->recordFailure(
                email: $email,
                reason: 'email_not_verified',
                user: $user,
                ipAddress: $context->ipAddress,
                userAgent: $context->userAgent,
                browser: $context->browser,
                device: $context->device,
            );

            throw new RuntimeException(
                'Email address is not verified.',
            );
        }

        /*
         * Verify the password.
         *
         * The plain password is never persisted.
         */
        if (! Hash::check(
            $context->password,
            (string) $user->password,
        )) {
            $attemptsBeforeFailure = $this->securityService
                ->remainingAttempts($email);

            $this->securityService->recordFailedAttempt(
                $email,
            );

            if ($attemptsBeforeFailure === 1) {
                $this->securityEventService->record(
                    event: SecurityEventType::BruteForceDetected,
                    reason: 'maximum_failed_authentication_attempts_reached',
                    user: $user,
                    ipAddress: $context->ipAddress,
                    userAgent: $context->userAgent,
                    browser: $context->browser,
                    device: $context->device,
                );

                $this->securityEventService->record(
                    event: SecurityEventType::AccountLocked,
                    reason: 'maximum_failed_authentication_attempts_reached',
                    user: $user,
                    ipAddress: $context->ipAddress,
                    userAgent: $context->userAgent,
                    browser: $context->browser,
                    device: $context->device,
                );
            }

            if ($context->ipAddress !== null) {
                $this->securityService->recordFailedAttemptByIp(
                    $context->ipAddress,
                );
            }

            $this->loginHistoryService->recordFailure(
                email: $email,
                reason: 'invalid_credentials',
                user: $user,
                ipAddress: $context->ipAddress,
                userAgent: $context->userAgent,
                browser: $context->browser,
                device: $context->device,
            );

            throw new RuntimeException(
                'Invalid credentials.',
            );
        }

        /*
         * Upgrade a valid legacy hash when the configured hashing
         * parameters have changed. This custom authentication flow uses
         * Hash::check() and Auth::login(), so Laravel's provider-level
         * rehash-on-login hook is not invoked automatically.
         *
         * Rehashing the same password is not a password change and must
         * not create a password-history entry.
         */
        if (
            (bool) config('hashing.rehash_on_login', true)
            && Hash::needsRehash((string) $user->password)
        ) {
            $user->forceFill([
                'password' => Hash::make($context->password),
            ])->save();
        }

        /*
         * Successful authentication.
         *
         * IMPORTANT:
         *
         * This service does not create the Web authentication
         * session and does not remember the device.
         *
         * Those operations belong to the authentication workflow
         * after Laravel regenerates the session ID.
         */
        $this->securityService->clearFailedAttempts(
            $email,
        );

        if ($context->ipAddress !== null) {
            $this->securityService->clearFailedIpAttempts(
                $context->ipAddress,
            );
        }

        return $user;
    }

    /**
     * Determine whether the account can authenticate.
     */
    private function isAccountActive(User $user): bool
    {
        return $user->status === UserStatus::Active;
    }
}
