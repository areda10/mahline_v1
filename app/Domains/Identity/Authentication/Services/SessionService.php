<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\Services;

use App\Core\Foundation\Services\BaseService;
use App\Domains\Identity\Authentication\Models\AuthenticationSession;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Support\Facades\DB;

final class SessionService extends BaseService
{
    public function __construct(
        private readonly LoginHistoryService $loginHistoryService,
    ) {
    }

    /**
     * Create a new authentication session.
     *
     * Architecture rule:
     *
     * ONE USER
     *      ↓
     * ONE ACTIVE AUTHENTICATION SESSION
     *
     * A user may have several historical authentication
     * sessions, but only one session can be active.
     *
     * When a new login occurs:
     *
     * 1. The previous active session is revoked.
     * 2. The revocation is recorded in LoginHistory.
     * 3. A NEW AuthenticationSession row is created.
     *
     * The previous session is therefore preserved as history.
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
             * Create a NEW authentication session.
             *
             * IMPORTANT:
             *
             * We deliberately create a new database row.
             *
             * The previous authentication session must remain
             * available as historical data.
             */
            $session = AuthenticationSession::query()->create([
                'user_id' => $user->getKey(),

                'session_id' => $sessionId,

                'ip_address' => $ipAddress,

                'user_agent' => $userAgent,

                'browser' => $browser,

                'device' => $device,

                'authenticated_at' => now(),

                'last_activity_at' => now(),

                'revoked_at' => null,

                'revocation_reason' => null,
            ]);

            /*
             * Return the newly created active session.
             */
            return $session->fresh();
        });
    }

    /**
     * Determine whether an authentication session is active.
     *
     * A session is active when:
     *
     * - it is not soft deleted;
     * - it has not been revoked.
     */
    public function isActive(
        AuthenticationSession $session,
    ): bool {
        return ! $session->trashed()
            && $session->revoked_at === null;
    }

    /**
     * Return the current active authentication session.
     */
    public function current(
        User $user,
    ): ?AuthenticationSession {
        return AuthenticationSession::query()
            ->where('user_id', $user->getKey())
            ->whereNull('revoked_at')
            ->latest('authenticated_at')
            ->first();
    }

    /**
     * Revoke the current authentication session of a user.
     */
    public function revokeForUser(
        User $user,
        string $reason = 'logout',
    ): bool {
        $session = $this->current($user);

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
     * A revoked session cannot be revoked again.
     */
    public function revoke(
        AuthenticationSession $session,
        string $reason = 'logout',
    ): bool {
        /*
         * Do not revoke an already revoked or deleted session.
         */
        if (! $this->isActive($session)) {
            return false;
        }

        /*
         * Mark the session as revoked.
         */
        $session->revoked_at = now();
        $session->revocation_reason = $reason;

        $session->save();

        /*
         * Retrieve the associated user.
         */
        $user = $session->user;

        if ($user !== null) {
            /*
             * Logout is recorded as a logout event.
             */
            if ($reason === 'logout') {
                $this->loginHistoryService->recordLogout(
                    user: $user,
                    session: $session,
                    ipAddress: $session->ip_address,
                    userAgent: $session->user_agent,
                    browser: $session->browser,
                    device: $session->device,
                );
            } else {
                /*
                 * Other revocation reasons are recorded as
                 * session_revoked.
                 */
                $this->loginHistoryService->recordSessionRevoked(
                    user: $user,
                    session: $session,
                    reason: $reason,
                    ipAddress: $session->ip_address,
                    userAgent: $session->user_agent,
                    browser: $session->browser,
                    device: $session->device,
                );
            }
        }

        return true;
    }

    /**
     * Update the last activity timestamp of an active session.
     */
    public function touch(
        AuthenticationSession $session,
    ): bool {
        /*
         * Only active sessions can receive activity updates.
         */
        if (! $this->isActive($session)) {
            return false;
        }

        $session->last_activity_at = now();

        $session->save();

        return true;
    }
}