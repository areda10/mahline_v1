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
     * Create or replace the authentication session of a user.
     *
     * Architecture rule:
     *
     * ONE USER
     *      ↓
     * ONE AUTHENTICATION SESSION
     *
     * A second login does not create a second database row.
     *
     * Instead, the existing authentication session is reused
     * and its session information is replaced.
     *
     * This guarantees:
     *
     * - one authentication_sessions row per user;
     * - one active session per user;
     * - stable authentication session primary key;
     * - complete login history through LoginHistoryService.
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
             * Search for the existing authentication session.
             *
             * withTrashed() is intentionally used because the
             * architecture uses SoftDeletes.
             */
            $session = AuthenticationSession::withTrashed()
                ->where('user_id', $user->getKey())
                ->first();

            /*
             * First authentication for this user.
             */
            if ($session === null) {
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

                return $session;
            }

            /*
             * If the previous record was soft deleted, restore it.
             *
             * We reuse the existing row instead of creating another
             * authentication session.
             */
            if ($session->trashed()) {
                $session->restore();
            }

            /*
             * If an active session already exists, this is a
             * new login from another browser/device/session.
             *
             * Record the previous session as revoked BEFORE
             * replacing its values.
             */
            if ($this->isActive($session)) {
                $this->loginHistoryService->recordSessionRevoked(
                    user: $user,
                    session: $session,
                    reason: 'new_login',
                    ipAddress: $session->ip_address,
                    userAgent: $session->user_agent,
                    browser: $session->browser,
                    device: $session->device,
                );
            }

            /*
             * Reuse the same authentication_sessions row.
             *
             * This is important:
             *
             * We DO NOT delete the previous row.
             * We DO NOT create a second row.
             *
             * The primary key therefore remains stable.
             */
            $session->session_id = $sessionId;
            $session->ip_address = $ipAddress;
            $session->user_agent = $userAgent;
            $session->browser = $browser;
            $session->device = $device;
            $session->authenticated_at = now();
            $session->last_activity_at = now();

            /*
             * The new login makes the session active again.
             */
            $session->revoked_at = null;
            $session->revocation_reason = null;

            $session->save();

            /*
             * Return the fresh model.
             */
            return $session->fresh();
        });
    }

    /**
     * Determine whether an authentication session is active.
     *
     * A session is active when:
     *
     * - it has not been revoked;
     * - it has not been soft deleted.
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
        $session = AuthenticationSession::query()
            ->where('user_id', $user->getKey())
            ->first();

        if ($session === null) {
            return null;
        }

        if (! $this->isActive($session)) {
            return null;
        }

        return $session;
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
     */
    public function revoke(
        AuthenticationSession $session,
        string $reason = 'logout',
    ): bool {
        /*
         * A revoked session cannot be revoked again.
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
         * LoginHistory is handled here for explicit revocation.
         *
         * The user relationship must exist for a valid
         * authentication session.
         */
        $user = $session->user;

        if ($user !== null) {
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
        if (! $this->isActive($session)) {
            return false;
        }

        $session->last_activity_at = now();

        $session->save();

        return true;
    }
}