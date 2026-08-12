<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\Services;

use App\Core\Foundation\Services\BaseService;
use App\Domains\Identity\Authentication\DTOs\AuthenticationContext;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

final class AuthenticationService extends BaseService
{
    public function __construct(
        private readonly SessionService $sessionService,
        private readonly LoginHistoryService $loginHistoryService,
    ) {
    }

    /**
     * Authenticate a user.
     *
     * Responsibilities:
     *
     * 1. Find the user by email.
     * 2. Verify that the account is active.
     * 3. Verify the password.
     * 4. Create the authentication session.
     * 5. Record the successful login.
     *
     * The service does not depend on Laravel's HTTP Request.
     *
     * AuthenticationContext transports all authentication
     * and client information required by the authentication layer.
     *
     * @throws ModelNotFoundException
     * @throws RuntimeException
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
         * We still record the failed authentication attempt.
         *
         * The password is NEVER stored or logged.
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

        /*
         * Verify the password.
         *
         * Hash::check() compares the supplied plain-text password
         * with the hashed password stored in the database.
         *
         * The plain-text password is never persisted.
         */
        if (! Hash::check(
            $context->password,
            (string) $user->password,
        )) {
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

        /*
         * Authentication succeeded.
         *
         * SessionService is responsible for enforcing:
         *
         * ONE USER → ONE ACTIVE AUTHENTICATED SESSION
         *
         * Therefore, if the user was already authenticated
         * on another device/browser, the previous session
         * is replaced/revoked according to SessionService's
         * rules.
         */
        $session = $this->sessionService->create(
            user: $user,
            sessionId: $context->sessionId,
            ipAddress: $context->ipAddress,
            userAgent: $context->userAgent,
            browser: $context->browser,
            device: $context->device,
        );

        /*
         * Record successful authentication.
         *
         * The LoginHistory entry references the newly created
         * authentication session.
         */
        $this->loginHistoryService->recordSuccess(
            user: $user,
            session: $session,
            ipAddress: $context->ipAddress,
            userAgent: $context->userAgent,
            browser: $context->browser,
            device: $context->device,
        );

        return $user;
    }

    /**
     * Determine whether the user account can authenticate.
     */
    private function isAccountActive(User $user): bool
    {
        return $user->status === UserStatus::Active;
    }
}