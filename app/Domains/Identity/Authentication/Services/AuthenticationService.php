<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\Services;

use App\Core\Foundation\Services\BaseService;
use App\Domains\Identity\Authentication\DTOs\AuthenticationContext;
use App\Domains\Identity\Authentication\Enums\SecurityEventType;
use App\Domains\Identity\Authentication\Models\AuthenticationSession;
use App\Domains\Identity\Authentication\Models\SecurityEvent;
use App\Domains\Identity\Authentication\Services\AuthenticationSecurityService;
use App\Domains\Identity\Authentication\Services\SecurityEventService;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

final class AuthenticationService extends BaseService
{
    public function __construct(
        private readonly SessionService $sessionService,
        private readonly LoginHistoryService $loginHistoryService,
        private readonly AuthenticationSecurityService $securityService,
        private readonly UnusualActivityDetectionService $unusualActivityDetectionService,
        private readonly SecurityEventService $securityEventService,
    ) {
    }

    /**
    * Authentication flow:
    *
    * 1. Find user by email.
    * 2. Reject unknown user.
    * 3. Verify account status.
    * 4. Verify password.
    * 5. Create a new authentication session.
    * 6. Record successful login.
    *
    * Existing active sessions are preserved.
    */
    public function authenticate(
        AuthenticationContext $context,
    ): User {
        /*
         * Find the user by email.
         */
        $user = User::query()
            ->where('email', $context->email)
            ->first();

        /*
         * Unknown user.
         *
         * No authentication session is created.
         */
        if ($user === null) {
            $this->loginHistoryService->recordFailure(
                email: $context->email,
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
                email: $context->email,
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

        if ($this->securityService->isLocked($context->email)) {
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
            $attemptsBeforeFailure = $this->securityService->remainingAttempts(
                $context->email,
            );
            $this->securityService->recordFailedAttempt(
                $context->email,
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
                email: $context->email,
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

        if (
            $context->ipAddress !== null
            && $this->securityService->isIpLocked($context->ipAddress)
        ) {
            throw new RuntimeException(
                'IP address is temporarily locked.'
            );
        }

        $session = DB::transaction(function () use (
            $user,
            $context,
        ): AuthenticationSession {
            $this->unusualActivityDetectionService->rememberDevice(
                user: $user,
                device: (string) $context->device,
            );

            return $this->sessionService->create(
                user: $user,
                sessionId: $context->sessionId,
                ipAddress: $context->ipAddress,
                userAgent: $context->userAgent,
                browser: $context->browser,
                device: $context->device,
            );
        });

        /*
         * Record the successful login.
         */
        $this->loginHistoryService->recordSuccess(
            user: $user,
            session: $session,
            ipAddress: $context->ipAddress,
            userAgent: $context->userAgent,
            browser: $context->browser,
            device: $context->device,
        );
        /**
         * Remise a zero compteur Attempts apres email ok
         */
        $this->securityService->clearFailedAttempts(
            $context->email,
        );

        /**
         * Remise a zero compteur Attempts apres email ok
         */
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