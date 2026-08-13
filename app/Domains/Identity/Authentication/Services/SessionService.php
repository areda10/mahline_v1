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
     * MAHLINE authentication policy:
     *
     * ONE USER → ONE ACTIVE AUTHENTICATED SESSION
     *
     * If the user already has an active session:
     *
     * 1. The previous session is revoked.
     * 2. A LoginHistory entry is created.
     * 3. The new session is created.
     *
     * The previous session is NOT deleted.
     *
     * This is important because authentication sessions are
     * part of the security history of the application.
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
             * Find the user's current active session.
             *
             * revoked_at IS NULL
             * deleted_at IS NULL
             */
            $previousSession = AuthenticationSession::query()
                ->where('user_id', $user->getKey())
                ->whereNull('revoked_at')
                ->first();

            /*
             * A previous active session exists.
             *
             * It must be revoked before creating the new session.
             */
            if ($previousSession !== null) {
                $this->revoke(
                    session: $previousSession,
                    reason: 'new_login',
                    recordHistory: true,
                );
            }

            /*
             * Create the new active authentication session.
             */
            return AuthenticationSession::query()->create([
                'user_id' => $user->getKey(),

                'session_id' => $sessionId,

                'authenticated_at' => now(),

                'last_activity_at' => now(),

                'revoked_at' => null,

                'revocation_reason' => null,
            ]);
        });
    }

    /**
     * Revoke the active authentication session for a user.
     *
     * Returns false when no active session exists.
     */
    public function revokeForUser(
        User $user,
        string $reason = 'logout',
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $browser = null,
        ?string $device = null,
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
            recordHistory: true,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            browser: $browser,
            device: $device,
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
        bool $recordHistory = true,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $browser = null,
        ?string $device = null,
    ): bool {
        /*
         * A revoked session cannot be revoked again.
         */
        if ($session->revoked_at !== null) {
            return false;
        }

        /*
         * Mark the session as revoked.
         */
        $session->forceFill([
            'revoked_at' => now(),
            'revocation_reason' => $reason,
        ]);

        $session->save();

        /*
         * Record the security event.
         *
         * new_login:
         * The user logged in from another device/browser.
         *
         * logout:
         * The user explicitly logged out.
         *
         * security:
         * The session was revoked for security reasons.
         */
        if ($recordHistory) {
            $user = $session->user;

            if ($user !== null) {
                $this->loginHistoryService->recordSessionRevoked(
                    user: $user,
                    session: $session,
                    reason: $reason,
                    ipAddress: $ipAddress,
                    userAgent: $userAgent,
                    browser: $browser,
                    device: $device,
                );
            }
        }

        return true;
    }

    /**
     * Determine whether an authentication session is active.
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

    /**
     * Update the last activity timestamp.
     *
     * A revoked or deleted session cannot be touched.
     */
    public function touch(
        AuthenticationSession $session,
    ): bool {
        if (! $this->isActive($session)) {
            return false;
        }

        $session->forceFill([
            'last_activity_at' => now(),
        ]);

        return $session->save();
    }

    /**
     * Get the current active session for a user.
     *
     * Returns null when the user has no active session.
     */
    public function current(
        User $user,
    ): ?AuthenticationSession {
        return AuthenticationSession::query()
            ->where('user_id', $user->getKey())
            ->whereNull('revoked_at')
            ->first();
    }
}