<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\Services;

use App\Core\Foundation\Services\BaseService;
use App\Domains\Identity\Authentication\Models\AuthenticationSession;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class SessionService extends BaseService
{
    public function __construct(
        private readonly LoginHistoryService $loginHistoryService,
    ) {
    }

    /**
     * Creates the active authentication session for a user.
     *
     * MAHLINE rule:
     *
     * ONE USER → ONE ACTIVE AUTHENTICATED SESSION
     *
     * Therefore, an existing session is revoked before
     * creating the new active session.
     */
    public function create(
        User $user,
        string $sessionId,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $browser = null,
        ?string $device = null,
    ): AuthenticationSession {
        return DB::transaction(function () use (
            $user,
            $sessionId,
            $ipAddress,
            $userAgent,
            $browser,
            $device,
        ): AuthenticationSession {
            /*
             * Find the existing authentication session.
             *
             * withTrashed() is intentionally used because the
             * authentication session table uses soft deletes.
             */
            $previousSession = AuthenticationSession::query()
                ->withTrashed()
                ->where('user_id', $user->getKey())
                ->first();

            /*
             * A new login replaces the previous session.
             */
            if ($previousSession !== null) {
                if ($this->isActive($previousSession)) {
                    $this->revoke(
                        session: $previousSession,
                        reason: 'new_login',
                    );
                }

                /*
                 * The unique user_id constraint means we cannot
                 * insert another row for the same user while the
                 * previous row still exists.
                 *
                 * Remove the previous row after recording its
                 * revocation state.
                 */
                if ($previousSession->exists) {
                    $previousSession->forceDelete();
                }
            }

            /*
             * Create the new authentication session.
             */
            $session = new AuthenticationSession();

            $session->user_id = $user->getKey();
            $session->session_id = $sessionId;
            $session->authenticated_at = now();
            $session->last_activity_at = now();
            $session->revoked_at = null;
            $session->revocation_reason = null;

            /*
             * Client information.
             */
            $session->ip_address = $ipAddress;
            $session->user_agent = $userAgent;
            $session->browser = $browser;
            $session->device = $device;

            $session->save();

            /*
             * Record successful authentication.
             */
            $this->loginHistoryService->recordSuccess(
                user: $user,
                session: $session,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                browser: $browser,
                device: $device,
            );

            return $session;
        });
    }

    /**
     * Determines whether an authentication session is active.
     */
    public function isActive(
        AuthenticationSession $session,
    ): bool {
        return $session->revoked_at === null
            && $session->deleted_at === null;
    }

    /**
     * Revokes a specific authentication session.
     */
    public function revoke(
        AuthenticationSession $session,
        string $reason,
    ): bool {
        if (! $this->isActive($session)) {
            return false;
        }

        $session->revoked_at = now();
        $session->revocation_reason = $reason;

        $session->save();

        /*
         * Record the lifecycle event.
         */
        $this->loginHistoryService->recordSessionRevoked(
            user: $session->user,
            session: $session,
            reason: $reason,
        );

        return true;
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
     * Updates the last activity timestamp.
     */
    public function touch(
        AuthenticationSession $session,
    ): bool {
        if (! $this->isActive($session)) {
            return false;
        }

        $session->last_activity_at = now();
        $session->save();

        return true;
    }
}