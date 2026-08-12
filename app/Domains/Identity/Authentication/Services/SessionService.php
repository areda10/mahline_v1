<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\Services;

use App\Core\Foundation\Services\BaseService;
use App\Domains\Identity\Authentication\Models\AuthenticationSession;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Support\Facades\DB;

final class SessionService extends BaseService
{
    /**
     * Create a new authentication session for a user.
     *
     * MAHLINE authentication rule:
     *
     * ONE USER → ONE ACTIVE AUTHENTICATED SESSION
     *
     * Therefore, when a user logs in from another device/browser:
     *
     * 1. The current active session is revoked.
     * 2. The revocation reason is "new_login".
     * 3. A new authentication session is created.
     *
     * The previous session is NOT deleted.
     * It remains available as authentication history.
     *
     * The complete operation is executed inside a database transaction
     * so that the user cannot end up with two active sessions.
     */
    public function create(
        User $user,
        string $sessionId,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $browser = null,
    ): AuthenticationSession {
        return DB::transaction(function () use (
            $user,
            $sessionId,
            $ipAddress,
            $userAgent,
            $browser,
        ): AuthenticationSession {
            /*
             * Lock the user's active authentication session.
             *
             * This prevents concurrent login requests from creating
             * two active sessions for the same user.
             */
            $activeSession = AuthenticationSession::query()
                ->where('user_id', $user->getKey())
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->first();

            /*
             * A user may already have an active session.
             *
             * A new login replaces that session.
             */
            if ($activeSession !== null) {
                $this->revoke(
                    session: $activeSession,
                    reason: 'new_login',
                );
            }

            /*
             * Create the new active authentication session.
             *
             * The model generates its own ULID.
             */
            return AuthenticationSession::query()->create([
                'user_id' => $user->getKey(),

                'session_id' => $sessionId,

                'ip_address' => $ipAddress,

                'user_agent' => $userAgent,

                'browser' => $browser,

                'authenticated_at' => now(),

                'last_activity_at' => now(),

                'revoked_at' => null,

                'revocation_reason' => null,
            ]);
        });
    }

    /**
     * Revoke the current active session of a user.
     *
     * Returns true when an active session was found and revoked.
     *
     * Returns false when the user has no active authentication session.
     */
    public function revokeForUser(
        User $user,
        string $reason,
    ): bool {
        $session = AuthenticationSession::query()
            ->where('user_id', $user->getKey())
            ->whereNull('revoked_at')
            ->first();

        if ($session === null) {
            return false;
        }

        return $this->revoke(
            session: $session,
            reason: $reason,
        );
    }

    /**
     * Revoke a specific authentication session.
     *
     * A session that has already been revoked cannot be revoked again.
     *
     * This protects the original revocation information.
     */
    public function revoke(
        AuthenticationSession $session,
        string $reason,
    ): bool {
        /*
         * A revoked session cannot be revoked again.
         */
        if ($session->revoked_at !== null) {
            return false;
        }

        /*
         * Store the revocation timestamp and reason.
         *
         * The session remains in the database for authentication history.
         */
        $session->forceFill([
            'revoked_at' => now(),
            'revocation_reason' => $reason,
        ]);

        $session->save();

        return true;
    }

    /**
     * Update the last activity timestamp of an active session.
     *
     * Revoked sessions cannot become active again.
     */
    public function touch(
        AuthenticationSession $session,
    ): bool {
        /*
         * A revoked session must never receive activity updates.
         */
        if (! $this->isActive($session)) {
            return false;
        }

        $session->forceFill([
            'last_activity_at' => now(),
        ]);

        $session->save();

        return true;
    }

    /**
     * Determine whether an authentication session is currently active.
     *
     * A session is active only when:
     *
     * - it has not been revoked;
     * - it has not been soft deleted.
     */
    public function isActive(
        AuthenticationSession $session,
    ): bool {
        return $session->revoked_at === null
            && $session->deleted_at === null;
    }
}