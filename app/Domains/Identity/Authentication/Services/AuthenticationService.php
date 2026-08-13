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
     * 1. Find the user by email.
     * 2. Record a failed attempt if the user does not exist.
     * 3. Verify that the account is active.
     * 4. Record a failed attempt if the account is inactive.
     * 5. Verify the password.
     * 6. Record a failed attempt if the password is invalid.
     * 7. Create the authentication session.
     * 8. Record the successful login.
     *
     * The service does not depend on Laravel's HTTP Request.
     *
     * AuthenticationContext contains all information required
     * to perform the authentication operation.
     *
     * ONE USER → ONE ACTIVE AUTHENTICATED SESSION
     *
     * SessionService is responsible for replacing/revoking
     * an existing active session when a new login occurs.
     *
     * @throws ModelNotFoundException
     * @throws RuntimeException
     */
    public function authenticate(
        AuthenticationContext $context,
    ): User {
        /*
         * ---------------------------------------------------------
         * 1. Find the user.
         * ---------------------------------------------------------
         *
         * We search by email only.
         *
         * The password is never logged or persisted.
         */
        $user = User::query()
            ->where('email', $context->email)
            ->first();

        /*
         * ---------------------------------------------------------
         * 2. Unknown user.
         * ---------------------------------------------------------
         *
         * We still record the failed authentication attempt.
         *
         * user_id remains NULL because the account does not exist.
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
         * ---------------------------------------------------------
         * 3. Verify account status.
         * ---------------------------------------------------------
         *
         * Only ACTIVE users can authenticate.
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
         * ---------------------------------------------------------
         * 4. Verify password.
         * ---------------------------------------------------------
         *
         * We use Laravel's Hash facade directly because
         * MAHLINE currently does not have a PasswordService.
         *
         * The plain-text password must NEVER be logged,
         * persisted, or included in LoginHistory.
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
         * ---------------------------------------------------------
         * 5. Authentication successful.
         * ---------------------------------------------------------
         *
         * SessionService handles the ONE USER → ONE ACTIVE
         * AUTHENTICATED SESSION rule.
         *
         * If another session already exists for this user:
         *
         * Android / Firefox
         *        ↓
         * existing session
         *        ↓
         * revoked with "new_login"
         *        ↓
         * new session created
         *
         * Therefore, a new login replaces the previous
         * active authentication session.
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
         * ---------------------------------------------------------
         * 6. Record successful authentication.
         * ---------------------------------------------------------
         *
         * LoginHistory stores the historical authentication event.
         *
         * The AuthenticationSession relation allows us to know
         * which session produced this successful login.
         */
        $this->loginHistoryService->recordSuccess(
            user: $user,
            session: $session,
            ipAddress: $context->ipAddress,
            userAgent: $context->userAgent,
            browser: $context->browser,
            device: $context->device,
        );

        /*
         * ---------------------------------------------------------
         * 7. Return authenticated user.
         * ---------------------------------------------------------
         */
        return $user;
    }

    /**
     * Determine whether the user account is active.
     *
     * The domain enum is used here instead of comparing
     * the database value manually.
     */
    private function isAccountActive(User $user): bool
    {
        return $user->status === UserStatus::Active;
    }
}