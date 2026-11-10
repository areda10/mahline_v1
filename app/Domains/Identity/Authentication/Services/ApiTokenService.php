<?php

declare(strict_types=1);

namespace App\\Domains\\Identity\\Authentication\\Services;

use App\\Core\\Foundation\\Services\\BaseService;
use App\\Domains\\Audit\\Services\\AuditService;
use App\\Domains\\Identity\\Authentication\\Models\\PersonalAccessToken;
use App\\Domains\\Identity\\Users\\Models\\User;
use App\\Shared\\DTOs\\AuditEntryDTO;
use Illuminate\\Database\\Eloquent\\Collection;
use Illuminate\\Support\\Facades\\DB;
use Laravel\\Sanctum\\NewAccessToken;
use RuntimeException;

final class ApiTokenService extends BaseService
{
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    /**
     * Create an API token for its owner only.
     *
     * Abilities default to an empty list until the API-06 role/ability matrix
     * is formally defined. The plaintext secret is returned only by Sanctum
     * in this creation response and is never written to Audit.
     */
    public function createForOwner(
        User $owner,
        User $actor,
        string $name,
        array $abilities = [],
    ): NewAccessToken {
        $name = trim($name);

        if ($name === '') {
            throw new RuntimeException('An API token name is required.');
        }

        if ((string) $owner->getKey() !== (string) $actor->getKey()) {
            throw new RuntimeException('An API token can only be created by its owner.');
        }

        return DB::transaction(function () use (
            $owner,
            $actor,
            $name,
            $abilities,
        ): NewAccessToken {
            $lockedOwner = User::query()
                ->whereKey($owner->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedOwner->isAuthorized()) {
                throw new RuntimeException('The account is not authorized to create API tokens.');
            }

            $newToken = $lockedOwner->createToken($name, $abilities);
            $token = $newToken->accessToken;

            $this->audit->record(new AuditEntryDTO(
                eventKey: 'identity.authentication.api_token_created',
                action: 'api_token_created',
                actorType: User::class,
                actorId: (string) $actor->getKey(),
                cooperativeId: $lockedOwner->cooperative_id
                    ? (string) $lockedOwner->cooperative_id
                    : null,
                subjectType: PersonalAccessToken::class,
                subjectId: (string) $token->getKey(),
                oldValues: [],
                newValues: [
                    'name' => $token->name,
                    'abilities' => $token->abilities ?? [],
                ],
                metadata: [
                    'user_id' => (string) $lockedOwner->getKey(),
                ],
            ));

            return $newToken;
        });
    }

    /**
     * Return all token records for one owner, newest first.
     *
     * Revoked records remain visible as history. The query is always scoped
     * to the supplied owner and never exposes the stored token hash.
     *
     * @return Collection<int, PersonalAccessToken>
     */
    public function tokensForUser(User $user): Collection
    {
        return PersonalAccessToken::query()
            ->where('tokenable_type', $user->getMorphClass())
            ->where('tokenable_id', $user->getKey())
            ->latest('created_at')
            ->get();
    }

    /**
     * Return safe token metadata. The secret and stored hash are excluded.
     *
     * @return array{
     *     id: string,
     *     name: string,
     *     abilities: array,
     *     created_at: ?string,
     *     last_used_at: ?string,
     *     expires_at: ?string,
     *     revoked_at: ?string,
     *     is_active: bool
     * }
     */
    public function metadata(PersonalAccessToken $token): array
    {
        $isActive = $token->revoked_at === null
            && ($token->expires_at === null || $token->expires_at->isFuture());

        return [
            'id' => (string) $token->getKey(),
            'name' => $token->name,
            'abilities' => $token->abilities ?? [],
            'created_at' => $token->created_at?->toISOString(),
            'last_used_at' => $token->last_used_at?->toISOString(),
            'expires_at' => $token->expires_at?->toISOString(),
            'revoked_at' => $token->revoked_at?->toISOString(),
            'is_active' => $isActive,
        ];
    }

    /**
     * Revoke all active API tokens owned by the given user.
     *
     * Token records are retained for history. Every token is revoked
     * individually and receives exactly one audit event. Web sessions
     * are deliberately outside this service.
     */
    public function revokeForUser(
        User $user,
        User $actor,
        string $reason,
        string $eventKey = 'identity.authentication.token_revoked',
        string $action = 'token_revoked',
    ): int {
        if (trim($reason) === '') {
            throw new RuntimeException(
                'An API token revocation reason is required.',
            );
        }

        return DB::transaction(function () use (
            $user,
            $actor,
            $reason,
            $eventKey,
            $action,
        ): int {
            $lockedUser = User::withTrashed()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $tokens = PersonalAccessToken::query()
                ->where('tokenable_type', $lockedUser->getMorphClass())
                ->where('tokenable_id', $lockedUser->getKey())
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->get();

            foreach ($tokens as $token) {
                $this->revokeLockedToken(
                    token: $token,
                    owner: $lockedUser,
                    actor: $actor,
                    reason: $reason,
                    eventKey: $eventKey,
                    action: $action,
                );
            }

            return $tokens->count();
        });
    }

    /**
     * Revoke one token while preserving its historical row.
     *
     * The token must belong to the supplied owner. Authorization to manage
     * another user's token is enforced by the calling workflow/controller.
     */
    public function revokeToken(
        User $owner,
        PersonalAccessToken $token,
        User $actor,
        string $reason = 'user_revoked',
        string $eventKey = 'identity.authentication.token_revoked',
        string $action = 'token_revoked',
    ): bool {
        if (trim($reason) === '') {
            throw new RuntimeException('An API token revocation reason is required.');
        }

        return DB::transaction(function () use (
            $owner,
            $token,
            $actor,
            $reason,
            $eventKey,
            $action,
        ): bool {
            $lockedOwner = User::withTrashed()
                ->whereKey($owner->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedToken = PersonalAccessToken::query()
                ->whereKey($token->getKey())
                ->where('tokenable_type', $lockedOwner->getMorphClass())
                ->where('tokenable_id', $lockedOwner->getKey())
                ->lockForUpdate()
                ->first();

            if ($lockedToken === null || $lockedToken->revoked_at !== null) {
                return false;
            }

            $this->revokeLockedToken(
                token: $lockedToken,
                owner: $lockedOwner,
                actor: $actor,
                reason: $reason,
                eventKey: $eventKey,
                action: $action,
            );

            return true;
        });
    }

    private function revokeLockedToken(
        PersonalAccessToken $token,
        User $owner,
        User $actor,
        string $reason,
        string $eventKey,
        string $action,
    ): void {
        $revokedAt = now();

        $token->revoked_at = $revokedAt;
        $token->save();

        $this->audit->record(new AuditEntryDTO(
            eventKey: $eventKey,
            action: $action,
            actorType: User::class,
            actorId: (string) $actor->getKey(),
            cooperativeId: $owner->cooperative_id
                ? (string) $owner->cooperative_id
                : null,
            subjectType: PersonalAccessToken::class,
            subjectId: (string) $token->getKey(),
            oldValues: ['revoked_at' => null],
            newValues: ['revoked_at' => $revokedAt->toISOString()],
            metadata: [
                'user_id' => (string) $owner->getKey(),
                'token_name' => $token->name,
                'reason' => $reason,
            ],
        ));
    }
}
