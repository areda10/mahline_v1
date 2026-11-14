<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\Services;

use App\Core\Foundation\Services\BaseService;
use App\Domains\Audit\Services\AuditService;
use App\Domains\Identity\Authentication\Models\AuthenticationSession;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use App\Shared\DTOs\AuditEntryDTO;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class SessionService extends BaseService
{
    public function __construct(
        private readonly LoginHistoryService $loginHistoryService,
        private readonly AuditService $audit,
        private readonly ApiTokenService $apiTokens,
    ) {}

    /**
     * Create a new session without revoking any existing session.
     * A user may have several active sessions on different devices.
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
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->first();

            if ($lockedUser === null) {
                throw new \RuntimeException(
                    'The user is no longer available for authentication.',
                );
            }

            if ($lockedUser->status !== UserStatus::Active) {
                throw new \RuntimeException(
                    'The user account is not active. A new session cannot be created.',
                );
            }

            if ($lockedUser->email_verified_at === null) {
                throw new \RuntimeException(
                    'The user email address is not verified. A new session cannot be created.',
                );
            }

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

            return $session->fresh();
        });
    }

    /**
     * Return the maximum server-side inactivity period for the user's role.
     *
     * When a user has multiple roles, the shortest applicable timeout wins.
     */
    public function inactivityTimeoutMinutes(User $user): int
    {
        return match (true) {
            $user->hasRole('super_admin_root') => 15,
            $user->hasRole('super_admin') => 30,
            $user->hasRole('cooperative_admin') => 30,
            $user->hasRole('customer') => 60,
            $user->hasRole('user') => 60,
            default => 60,
        };
    }

    /**
     * Atomically expire a session when its role-based inactivity limit is reached.
     * The row lock prevents a concurrent activity update from racing revocation.
     */
    public function expireIfInactive(
        User $user,
        AuthenticationSession $session,
    ): bool {
        return DB::transaction(function () use ($user, $session): bool {
            $lockedSession = AuthenticationSession::query()
                ->whereKey($session->getKey())
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->first();

            if ($lockedSession === null || ! $this->isActive($lockedSession)) {
                return false;
            }

            if (! $this->hasExceededInactivityLimit($user, $lockedSession)) {
                return false;
            }

            return $this->revoke($lockedSession, 'inactivity_timeout');
        });
    }

    /**
     * Determine whether the authentication session exceeded its role-based
     * server-side inactivity limit. This does not depend on cookie lifetime.
     */
    public function hasExceededInactivityLimit(
        User $user,
        AuthenticationSession $session,
    ): bool {
        $lastActivity = $session->last_activity_at ?? $session->authenticated_at;

        return $lastActivity->lessThanOrEqualTo(
            now()->subMinutes($this->inactivityTimeoutMinutes($user)),
        );
    }

    /**
     * Return sessions that are active now under the same role-based inactivity
     * policy enforced by EnsureActiveAuthenticationSession. Listing sessions
     * never revokes or mutates them.
     *
     * @return Collection<int, AuthenticationSession>
     */
    public function activeSessionsFor(User $user): Collection
    {
        $cutoff = now()->subMinutes($this->inactivityTimeoutMinutes($user));

        return AuthenticationSession::query()
            ->where('user_id', $user->getKey())
            ->whereNull('revoked_at')
            ->where(function ($query) use ($cutoff): void {
                $query->where('last_activity_at', '>', $cutoff)
                    ->orWhere(function ($fallback) use ($cutoff): void {
                        $fallback->whereNull('last_activity_at')
                            ->where('authenticated_at', '>', $cutoff);
                    });
            })
            ->orderByDesc('last_activity_at')
            ->get();
    }

    public function isActive(AuthenticationSession $session): bool
    {
        return ! $session->trashed() && $session->revoked_at === null;
    }

    public function current(User $user, string $sessionId): ?AuthenticationSession
    {
        return AuthenticationSession::query()
            ->where('user_id', $user->getKey())
            ->where('session_id', $sessionId)
            ->whereNull('revoked_at')
            ->latest('authenticated_at')
            ->first();
    }

    public function findActiveById(
        User $user,
        string $authenticationSessionId,
    ): ?AuthenticationSession {
        return AuthenticationSession::query()
            ->whereKey($authenticationSessionId)
            ->where('user_id', $user->getKey())
            ->whereNull('revoked_at')
            ->first();
    }

    /**
     * Revoke every active session. Each session gets exactly one
     * LoginHistory and one Audit event through revoke().
     */
    public function revokeForUser(User $user, string $reason = 'logout'): bool
    {
        $sessions = AuthenticationSession::query()
            ->where('user_id', $user->getKey())
            ->whereNull('revoked_at')
            ->get();

        if ($sessions->isEmpty()) {
            return false;
        }

        $revoked = false;

        foreach ($sessions as $session) {
            if ($this->revoke($session, $reason)) {
                $revoked = true;
            }
        }

        return $revoked;
    }

    /**
     * Revoke every active web session and API token owned by one user.
     *
     * Each web session is revoked through revoke(), preserving its login
     * history and audit trail. API tokens are individually marked revoked
     * and receive an immutable audit event; token secrets are never logged.
     *
     * @return array{sessions: int, tokens: int}
     */
    public function revokeAllAccessForUser(User $user): array
    {
        return DB::transaction(function () use ($user): array {
            /*
             * SessionService::create() locks the same user row. This serializes
             * global revocation against a concurrent web login so a new
             * session cannot slip into the active-session set mid-operation.
             */
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $activeSessionCount = AuthenticationSession::query()
                ->where('user_id', $lockedUser->getKey())
                ->whereNull('revoked_at')
                ->count();

            $this->revokeForUser($lockedUser, 'global_logout');

            $actor = auth()->user();
            $actor = $actor instanceof User ? $actor : $lockedUser;

            $revokedTokenCount = $this->revokeApiTokensForUser(
                user: $lockedUser,
                actor: $actor,
                reason: 'global_logout',
            );

            $actor = auth()->user();
            $actor = $actor instanceof User ? $actor : null;

            $this->audit->record(new AuditEntryDTO(
                eventKey: 'identity.authentication.global_logout',
                action: 'global_logout',
                actorType: $actor ? User::class : null,
                actorId: $actor ? (string) $actor->getKey() : null,
                cooperativeId: $lockedUser->cooperative_id
                    ? (string) $lockedUser->cooperative_id
                    : null,
                subjectType: User::class,
                subjectId: (string) $lockedUser->getKey(),
                oldValues: null,
                newValues: [
                    'active_sessions_revoked' => $activeSessionCount,
                    'api_tokens_revoked' => $revokedTokenCount,
                ],
                metadata: [
                    'user_id' => (string) $lockedUser->getKey(),
                ],
            ));

            return [
                'sessions' => $activeSessionCount,
                'tokens' => $revokedTokenCount,
            ];
        });
    }

    /**
     * Revoke every active Sanctum token for a user without deleting its
     * historical record. Each token receives an immutable audit event.
     */
    public function revokeApiTokensForUser(
        User $user,
        User $actor,
        string $reason,
    ): int {
        return $this->apiTokens->revokeForUser(
            user: $user,
            actor: $actor,
            reason: $reason,
        );
    }

    /**
     * Revoke every active session except the one currently completing
     * a mandatory password change.
     */
    public function revokeOtherSessions(
        User $user,
        string $currentAuthenticationSessionId,
        string $reason = 'logout',
    ): bool {
        $sessions = AuthenticationSession::query()
            ->where('user_id', $user->getKey())
            ->whereKeyNot($currentAuthenticationSessionId)
            ->whereNull('revoked_at')
            ->get();

        $revoked = false;

        foreach ($sessions as $session) {
            if ($this->revoke($session, $reason)) {
                $revoked = true;
            }
        }

        return $revoked;
    }

    /**
     * Revoke one session and atomically persist its specialized
     * LoginHistory record and its immutable Audit event.
     */
    public function revoke(
        AuthenticationSession $session,
        string $reason = 'logout',
    ): bool {
        return DB::transaction(function () use ($session, $reason): bool {
            $lockedSession = AuthenticationSession::query()
                ->whereKey($session->getKey())
                ->lockForUpdate()
                ->first();

            if ($lockedSession === null || ! $this->isActive($lockedSession)) {
                return false;
            }

            $lockedSession->revoked_at = now();
            $lockedSession->revocation_reason = $reason;
            $lockedSession->save();

            $user = $lockedSession->user;

            if ($user !== null) {
                if ($reason === 'logout') {
                    $this->loginHistoryService->recordLogout(
                        user: $user,
                        session: $lockedSession,
                        ipAddress: $lockedSession->ip_address,
                        userAgent: $lockedSession->user_agent,
                        browser: $lockedSession->browser,
                        device: $lockedSession->device,
                    );
                } else {
                    $this->loginHistoryService->recordSessionRevoked(
                        user: $user,
                        session: $lockedSession,
                        reason: $reason,
                        ipAddress: $lockedSession->ip_address,
                        userAgent: $lockedSession->user_agent,
                        browser: $lockedSession->browser,
                        device: $lockedSession->device,
                    );
                }
            }

            $actor = auth()->user();
            $actor = $actor instanceof User ? $actor : null;

            $this->audit->record(new AuditEntryDTO(
                eventKey: $reason === 'logout'
                    ? 'identity.authentication.logout'
                    : 'identity.authentication.session_revoked',
                action: $reason === 'logout' ? 'logout' : 'session_revoked',
                actorType: $actor ? User::class : null,
                actorId: $actor ? (string) $actor->getKey() : null,
                cooperativeId: $user?->cooperative_id
                    ? (string) $user->cooperative_id
                    : null,
                subjectType: AuthenticationSession::class,
                subjectId: (string) $lockedSession->getKey(),
                oldValues: ['revoked_at' => null],
                newValues: [
                    'revoked_at' => $lockedSession->revoked_at?->toISOString(),
                    'revocation_reason' => $reason,
                ],
                metadata: [
                    'user_id' => $user ? (string) $user->getKey() : null,
                    'device' => $lockedSession->device,
                    'browser' => $lockedSession->browser,
                ],
                ipAddress: $lockedSession->ip_address,
                userAgent: $lockedSession->user_agent,
            ));

            $session->setRawAttributes($lockedSession->getAttributes(), true);

            return true;
        });
    }

    /**
     * Extend the current session only while it is still within its inactivity
     * window. An expired or revoked session can never be revived.
     */
    public function extendIfActive(
        User $user,
        AuthenticationSession $session,
    ): bool {
        return DB::transaction(function () use ($user, $session): bool {
            $lockedSession = AuthenticationSession::query()
                ->whereKey($session->getKey())
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->first();

            if ($lockedSession === null || ! $this->isActive($lockedSession)) {
                return false;
            }

            if ($this->hasExceededInactivityLimit($user, $lockedSession)) {
                $this->revoke($lockedSession, 'inactivity_timeout');

                return false;
            }

            $lockedSession->last_activity_at = now();
            $lockedSession->save();
            $session->setRawAttributes($lockedSession->getAttributes(), true);

            return true;
        });
    }

    public function touch(AuthenticationSession $session): bool
    {
        return DB::transaction(function () use ($session): bool {
            $lockedSession = AuthenticationSession::query()
                ->whereKey($session->getKey())
                ->lockForUpdate()
                ->first();

            if ($lockedSession === null || ! $this->isActive($lockedSession)) {
                return false;
            }

            $lockedSession->last_activity_at = now();
            $lockedSession->save();
            $session->setRawAttributes($lockedSession->getAttributes(), true);

            return true;
        });
    }
}
