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
        private readonly UnusualActivityDetectionService $unusualActivityDetectionService,
        private readonly SecurityEventService $securityEventService,
    ) {
    }

    /**
     * Authenticate a user.
     *
     * This service is responsible for authentication and security
     * verification only.
     *
     * Creation of the Web authentication session is intentionally
     * handled by the HTTP layer after Laravel regenerates the
     * session ID.
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
         * Unknown user.
         *
         * No authentication session is created.
         */
        if ($user === null) {
            $this->loginHistoryService->recordFailure(
                email: $email,
                reason: 'user_not_found',
                user: null,
                ipAddress: $context->ipAddress,
                userAgent: $context->userAgent,
                browser: $context->browser,
                device: $context->device,
            );

            throw (new ModelNotFoundException())
                ->setModel(
                    User::class,
                    [$context->email],
                );
        }

        /*
         * The account must be active.
         */
        if (! $this->isAccountActive($user)) {
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
                'User account is not active.'
            );
        }

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
                'IP address is temporarily locked.'
            );
        }

        /*
         * Verify account lock.
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
                'Account is temporarily locked.'
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
                'Invalid credentials.'
            );
        }

        /*
         * Successful authentication.
         *
         * The device is remembered here, but the Web authentication
         * session is deliberately NOT created here.
         */
        $this->unusualActivityDetectionService->rememberDevice(
            user: $user,
            device: (string) $context->device,
        );

        /*
         * Reset failed authentication counters.
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
