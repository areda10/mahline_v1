<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\Services;

use App\Core\Foundation\Services\BaseService;
use App\Core\Security\Services\PasswordService;
use App\Domains\Identity\Authentication\DTOs\AuthenticationContext;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use RuntimeException;

final class AuthenticationService extends BaseService
{
    public function __construct(
        private readonly PasswordService $passwordService,
        private readonly SessionService $sessionService,
        private readonly LoginHistoryService $loginHistoryService,
    ) {
    }

    /**
     * Authenticates a user.
     *
     * This service:
     *
     * 1. Finds the user.
     * 2. Verifies that the account is active.
     * 3. Verifies the password.
     * 4. Delegates session creation to SessionService.
     *
     * AuthenticationContext keeps the service independent
     * from Laravel's HTTP Request.
     *
     * @throws ModelNotFoundException
     * @throws RuntimeException
     */
    public function authenticate(
        AuthenticationContext $context,
    ): User {
        $user = User::query()
            ->where('email', $context->email)
            ->first();

        /*
         * Unknown user.
         *
         * We intentionally do not record the password.
         */
        if ($user === null) {
            $this->loginHistoryService->recordFailed(
                email: $context->email,
                ipAddress: $context->ipAddress,
                userAgent: $context->userAgent,
                browser: $context->browser,
                device: $context->device,
            );

            throw (new ModelNotFoundException())
                ->setModel(User::class, [$context->email]);
        }

        /*
         * Account must be active.
         */
        if (! $this->isAccountActive($user)) {
            $this->loginHistoryService->recordFailed(
                email: $context->email,
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
         */
        if (! $this->passwordService->verify(
            $context->password,
            (string) $user->password,
        )) {
            $this->loginHistoryService->recordFailed(
                email: $context->email,
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
         */
        $this->sessionService->create(
            user: $user,
            sessionId: $context->sessionId,
            ipAddress: $context->ipAddress,
            userAgent: $context->userAgent,
            browser: $context->browser,
            device: $context->device,
        );

        return $user;
    }

    /**
     * Determines whether the user account can authenticate.
     */
    private function isAccountActive(User $user): bool
    {
        return $user->status === UserStatus::Active;
    }
}