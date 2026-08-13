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
     * Authentication flow:
     *
     * 1. Find user by email.
     * 2. Reject unknown user.
     * 3. Verify account status.
     * 4. Verify password.
     * 5. Create/replace authentication session.
     * 6. Record successful login.
     *
     * The service does not depend on Laravel's HTTP Request.
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

        /*
         * Verify the password.
         *
         * The plain password is never persisted.
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
         * SessionService enforces:
         *
         * ONE USER → ONE ACTIVE AUTHENTICATED SESSION
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