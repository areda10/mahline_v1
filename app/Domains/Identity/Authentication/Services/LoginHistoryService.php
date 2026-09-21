<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\Services;

use App\Core\Foundation\Services\BaseService;
use App\Domains\Identity\Authentication\Models\AuthenticationSession;
use App\Domains\Identity\Authentication\Models\LoginHistory;
use App\Domains\Identity\Users\Models\User;

final class LoginHistoryService extends BaseService
{
    /**
     * Record a successful authentication.
     *
     * This method creates an immutable authentication history
     * record representing a successful login.
     */
    public function recordSuccess(
        User $user,
        AuthenticationSession $session,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $browser = null,
        ?string $device = null,
    ): LoginHistory {
        return $this->record(
            user: $user,
            email: $user->email,
            event: 'success',
            reason: null,
            session: $session,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            browser: $browser,
            device: $device,
        );
    }

    /**
     * Record a failed authentication attempt.
     *
     * The user is nullable because the email may not belong
     * to an existing account.
     */
    public function recordFailure(
        string $email,
        string $reason = 'invalid_credentials',
        ?User $user = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $browser = null,
        ?string $device = null,
    ): LoginHistory {
        return $this->record(
            user: $user,
            email: $email,
            event: 'failed',
            reason: $reason,
            session: null,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            browser: $browser,
            device: $device,
        );
    }

    /**
     * Record a logout event.
     */
    public function recordLogout(
        User $user,
        AuthenticationSession $session,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $browser = null,
        ?string $device = null,
    ): LoginHistory {
        return $this->record(
            user: $user,
            email: $user->email,
            event: 'logout',
            reason: 'logout',
            session: $session,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            browser: $browser,
            device: $device,
        );
    }

    /**
     * Record a session revocation.
     *
     * A session may be revoked individually or as part of a
     * global revocation operation.
     *
     * Typical reasons include:
     *
     * - logout
     * - password_changed
     * - password_reset
     * - security_incident
     * - global_logout
    */
    public function recordSessionRevoked(
        User $user,
        AuthenticationSession $session,
        string $reason = 'new_login',
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $browser = null,
        ?string $device = null,
    ): LoginHistory {
        return $this->record(
            user: $user,
            email: $user->email,
            event: 'session_revoked',
            reason: $reason,
            session: $session,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            browser: $browser,
            device: $device,
        );
    }

    /**
     * Store a login history record.
     *
     * This is the central persistence method used by all
     * public recording methods.
     */
    private function record(
        ?User $user,
        string $email,
        string $event,
        ?string $reason,
        ?AuthenticationSession $session,
        ?string $ipAddress,
        ?string $userAgent,
        ?string $browser,
        ?string $device,
    ): LoginHistory {
        return LoginHistory::query()->create([
            'user_id' => $user?->getKey(),

            'email' => $email,

            'event' => $event,

            'reason' => $reason,

            'authentication_session_id' => $session?->getKey(),

            'ip_address' => $ipAddress,

            'user_agent' => $userAgent,

            'browser' => $browser,

            'device' => $device,

            'occurred_at' => now(),
        ]);
    }
}