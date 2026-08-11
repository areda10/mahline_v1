<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\Services;

use App\Core\Foundation\Services\BaseService;
use App\Core\Security\Services\PasswordService;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use RuntimeException;

final class AuthenticationService extends BaseService
{
    public function __construct(
        private readonly PasswordService $passwordService,
    ) {
    }

    /**
     * Authenticate a user using email and password.
     *
     * This service does not depend on the HTTP Request.
     *
     * Session management is handled separately by the
     * authentication/session layer.
     *
     * @throws ModelNotFoundException
     * @throws RuntimeException
     */
    public function authenticate(
        string $email,
        string $password,
    ): User {
        $user = User::query()
            ->where('email', $email)
            ->first();

        if ($user === null) {
            throw (new ModelNotFoundException())
                ->setModel(User::class, [$email]);
        }

        if (! $this->isAccountActive($user)) {
            throw new RuntimeException(
                'User account is not active.'
            );
        }

        if (! $this->passwordService->verify(
            $password,
            (string) $user->password,
        )) {
            throw new RuntimeException(
                'Invalid credentials.'
            );
        }

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