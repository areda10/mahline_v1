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
     * Create an authentication session.
     *
     * Architecture rule:
     *
     * ONE USER → ONE ACTIVE AUTHENTICATED SESSION
     *
     * Because authentication_sessions.user_id is UNIQUE,
     * the previous session must be removed before a new
     * session can be created.
     *
     * The previous session's historical event is preserved
     * in LoginHistory.
     */
    public function create(
        User $user,
        string $sessionId,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $browser = null,
        ?string $device = null,
    ): AuthenticationSession {
        return DB::transaction(
            function () use (
                $user,
                $sessionId,
                $ipAddress,
                $userAgent,
                $browser,
                $device,
            ): AuthenticationSession {
                /*
                 * Find the current session.
                 */
                $previousSession = AuthenticationSession::query()
                    ->where('user_id', $user->getKey())
                    ->first();

                /*
                 * A new login replaces the existing session.
                 */
                if ($previousSession !== null) {
                    /*
                     * Record the historical revocation before
                     * deleting the current authentication session.
                     *
                     * LoginHistory keeps the audit trail.
                     */
                    $this->loginHistoryService->recordSessionRevoked(
                        user: $user,
                        session: $previousSession,
                        reason: 'new_login',
                        ipAddress: $ipAddress,
                        userAgent: $userAgent,
                        browser: $browser,
                        device: $device,
                    );

                    /*
                     * Mark the session as revoked first.
                     */
                    $previousSession->revoked_at = now();
                    $previousSession->revocation_reason = 'new_login';
                    $previousSession->save();

                    /*
                     * The database has a UNIQUE constraint on user_id.
                     *
                     * Therefore the old row must be removed before
                     * creating the new active authentication session.
                     *
                     * The historical event remains available in
                     * login_histories.
                     */
                    $previousSession->forceDelete();
                }

                /*
                 * Create the new active authentication session.
                 */
                return AuthenticationSession::query()->create([
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
            }
        );
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
            ->first();
    }

    /**
     * Determine whether a session is active.
     */
    public function isActive(
        AuthenticationSession $session,
    ): bool {
        return $session->revoked_at === null
            && $session->deleted_at === null;
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
     * A session can only be revoked once.
     *
     * When recordHistory is true, a corresponding LoginHistory
     * record is created.
     */
    public function revoke(
        AuthenticationSession $session,
        string $reason = 'logout',
    ): bool {
        /*
         * A session that is already revoked cannot be revoked again.
         */
        if ($session->revoked_at !== null) {
            return false;
        }

        /*
         * Mark the session as revoked.
         */
        $session->revoked_at = now();
        $session->revocation_reason = $reason;
        $session->save();

        /*
         * Record logout/security revocation history.
         *
         * "new_login" is already recorded by create()
         * because the previous session is replaced there.
         */
        if (
            $reason !== 'new_login'
            && $session->user !== null
        ) {
            $this->loginHistoryService->recordSessionRevoked(
                user: $session->user,
                session: $session,
                reason: $reason,
            );
        }

        return true;
    }

    /**
     * Update the last activity timestamp.
     */
    public function touch(
        AuthenticationSession $session,
    ): bool {
        /*
         * Revoked sessions cannot be touched.
         */
        if (! $this->isActive($session)) {
            return false;
        }

        $session->last_activity_at = now();
        $session->save();

        return true;
    }
}