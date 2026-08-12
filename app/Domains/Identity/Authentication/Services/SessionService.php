<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\Services;

use App\Core\Foundation\Services\BaseService;
use App\Domains\Identity\Authentication\Models\AuthenticationSession;
use App\Domains\Identity\Users\Models\User;

final class SessionService extends BaseService
{
    /**
     * Creates or replaces the authenticated session for a user.
     *
     * Architectural rule:
     *
     * ONE USER → ONE ACTIVE AUTHENTICATED SESSION
     *
     * The existing authentication session is revoked before
     * the new authentication session becomes active.
     */
    public function create(
        User $user,
        string $sessionId,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $browser = null,
    ): AuthenticationSession {
        $existingSession = AuthenticationSession::query()
            ->where('user_id', $user->getKey())
            ->withTrashed()
            ->first();

        /*
        if ($existingSession !== null) {
            $existingSession->forceFill([
                'revoked_at' => now(),
                'revocation_reason' => 'new_login',
            ]);

            $existingSession->save();

            /*
             * The database enforces ONE USER → ONE SESSION.
             *
             * We therefore remove the old row before creating
             * the new authenticated session.
             */
        /*    $existingSession->delete();
        }
        */
        if ($existingSession !== null) {
            $existingSession->restore();

            $existingSession->forceFill([
                'session_id' => $sessionId,
                'authenticated_at' => now(),
                'last_activity_at' => now(),
                'revoked_at' => null,
                'revocation_reason' => null,
            ]);

            $existingSession->save();

            return $existingSession->refresh();
        }

        return AuthenticationSession::query()->create([
            'user_id' => $user->getKey(),
            'session_id' => $sessionId,
            'authenticated_at' => now(),
            'last_activity_at' => now(),
            'revoked_at' => null,
            'revocation_reason' => null,
        ]);
    }

    /**
     * Revokes the current authentication session of a user.
     */
    public function revokeForUser(
        User $user,
        string $reason = 'logout',
    ): bool {
        $session = AuthenticationSession::query()
            ->where('user_id', $user->getKey())
            ->whereNull('revoked_at')
            ->first();

        if ($session === null) {
            return false;
        }

        $session->forceFill([
            'revoked_at' => now(),
            'revocation_reason' => $reason,
        ]);

        return $session->save();
    }

    /**
     * Revokes a specific authentication session.
     */
    public function revoke(
        AuthenticationSession $session,
        string $reason = 'logout',
    ): bool {
        if ($session->revoked_at !== null) {
            return false;
        }

        $session->forceFill([
            'revoked_at' => now(),
            'revocation_reason' => $reason,
        ]);

        return $session->save();
    }

    /**
     * Determines whether an authentication session is active.
     */
    public function isActive(AuthenticationSession $session): bool
    {
        return $session->revoked_at === null
            && $session->deleted_at === null;
    }

    /**
     * Updates the last activity timestamp.
     */
    public function touch(AuthenticationSession $session): bool
    {
        if (! $this->isActive($session)) {
            return false;
        }

        $session->forceFill([
            'last_activity_at' => now(),
        ]);

        return $session->save();
    }
}