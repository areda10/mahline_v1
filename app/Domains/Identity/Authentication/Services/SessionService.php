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
     * MULTIPLE ACTIVE AUTHENTICATION SESSIONS
     *
     * A user may have several active sessions simultaneously,
     * for example on different browsers or devices.
     *
     * Each authentication session has its own lifecycle and
     * can be revoked individually.
     *
     * Creating a new session does not revoke existing sessions.
     *
     * The new authentication session is persisted as a new
     * database row and returned as the active session.
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
     * Revoke all active authentication sessions of a user.
     */
    public function revokeForUser(
    User $user,
    string $reason = 'logout',
    ): bool {
        $sessions = AuthenticationSession::query()
            ->where('user_id', $user->getKey())
            ->whereNull('revoked_at')
            ->get();

        if ($sessions->isEmpty()) {
            return false;
        }

        $revoked = false;

        foreach ($sessions as $session) {
            if ($this->revoke(
                session: $session,
                reason: $reason,
            )) {
                $revoked = true;
            }
        }

        return $revoked;
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