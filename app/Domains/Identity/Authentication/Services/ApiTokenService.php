<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\Services;

use App\Core\Foundation\Services\BaseService;
use App\Domains\Audit\Services\AuditService;
use App\Domains\Identity\Authentication\Models\PersonalAccessToken;
use App\Domains\Identity\Users\Models\User;
use App\Shared\DTOs\AuditEntryDTO;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;
use RuntimeException;

final class ApiTokenService extends BaseService
{
    public const int RETENTION_DAYS_AFTER_EXPIRY = 90;

    /** @var array<string, int> */
    private const ACTIVE_TOKEN_LIMITS = [
        'super_admin_root' => 10,
        'super_admin' => 5,
        'cooperative_admin' => 5,
        'user' => 3,
    ];
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    /**
     * Create an API token for its owner only.
     *
     * Abilities are existing MAHLINE permission slugs granted to the owner.
     * The plaintext secret is returned only by Sanctum
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

            $allowedAbilities = $this->availableAbilities($lockedOwner);
            $requestedAbilities = array_values(array_unique($abilities));
            $invalidAbilities = array_diff($requestedAbilities, $allowedAbilities);

            if ($invalidAbilities !== [] || in_array('*', $requestedAbilities, true)) {
                throw ValidationException::withMessages([
                    'abilities' => 'Only active MAHLINE permissions granted to your account may be assigned to an API token.',
                ]);
            }

            $activeTokenCount = PersonalAccessToken::query()
                ->where('tokenable_type', $lockedOwner->getMorphClass())
                ->where('tokenable_id', $lockedOwner->getKey())
                ->whereNull('revoked_at')
                ->whereNotNull('expires_at')
                ->where('expires_at', '>', now())
                ->count();

            $tokenLimit = $this->tokenLimitForUser($lockedOwner);

            if ($tokenLimit === 0 || $activeTokenCount >= $tokenLimit) {
                throw ValidationException::withMessages([
                    'quota' => sprintf('Your account has reached its limit of %d active API tokens.', $tokenLimit),
                ]);
            }

            $newToken = $lockedOwner->createToken(
                $name,
                $abilities,
                now()->addDays(PersonalAccessToken::EXPIRATION_DAYS),
            );
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
                    'expires_at' => $token->expires_at?->toISOString(),
                ],
                metadata: [
                    'user_id' => (string) $lockedOwner->getKey(),
                ],
            ));

            return $newToken;
        });
    }

    /**
     * Return the active MAHLINE permission slugs granted through active roles.
     *
     * @return array<int, string>
     */
    public function availableAbilities(User $user): array
    {
        return $user->roles()
            ->where('roles.is_active', true)
            ->with(['permissions' => static fn ($query) => $query->where('permissions.is_active', true)])
            ->get()
            ->flatMap(static fn ($role) => $role->permissions->pluck('slug'))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public function tokenLimitForUser(User $user): int
    {
        $limits = $user->roles()
            ->where('roles.is_active', true)
            ->pluck('slug')
            ->map(static fn (string $slug): int => self::ACTIVE_TOKEN_LIMITS[$slug] ?? 0)
            ->all();

        return $limits === [] ? 0 : max($limits);
    }

    public function activeTokenCount(User $user): int
    {
        return PersonalAccessToken::query()
            ->where('tokenable_type', $user->getMorphClass())
            ->where('tokenable_id', $user->getKey())
            ->whereNull('revoked_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now())
            ->count();
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
            && $token->expires_at !== null
            && $token->expires_at->isFuture();

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


    /**
     * Permanently remove API tokens only after their 90-day post-expiry
     * retention period. Audit events are written before deletion and are
     * intentionally independent from the token row.
     *
     * Revocation does not shorten retention. Tokens without expires_at are
     * excluded. Web authentication sessions are never queried or modified.
     */
    public function pruneExpiredTokens(int $chunkSize = 500): int
    {
        if ($chunkSize < 1 || $chunkSize > 5000) {
            throw new RuntimeException('The API token purge chunk size must be between 1 and 5000.');
        }

        $cutoff = now()->subDays(self::RETENTION_DAYS_AFTER_EXPIRY);
        $deleted = 0;

        PersonalAccessToken::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $cutoff)
            ->chunkById($chunkSize, function (Collection $tokens) use (&$deleted): void {
                foreach ($tokens as $token) {
                    DB::transaction(function () use ($token, &$deleted): void {
                        $lockedToken = PersonalAccessToken::query()
                            ->whereKey($token->getKey())
                            ->whereNotNull('expires_at')
                            ->where('expires_at', '<=', now()->subDays(PersonalAccessToken::EXPIRATION_DAYS))
                            ->lockForUpdate()
                            ->first();

                        if ($lockedToken === null) {
                            return;
                        }

                        $this->audit->record(new AuditEntryDTO(
                            eventKey: 'identity.authentication.api_token_purged',
                            action: 'api_token_purged',
                            subjectType: PersonalAccessToken::class,
                            subjectId: (string) $lockedToken->getKey(),
                            oldValues: [
                                'expires_at' => $lockedToken->expires_at?->toISOString(),
                                'revoked_at' => $lockedToken->revoked_at?->toISOString(),
                            ],
                            metadata: [
                                'token_name' => $lockedToken->name,
                                'tokenable_type' => $lockedToken->tokenable_type,
                                'tokenable_id' => (string) $lockedToken->tokenable_id,
                                'retention_days_after_expiry' => self::RETENTION_DAYS_AFTER_EXPIRY,
                            ],
                        ));

                        $lockedToken->delete();
                        $deleted++;
                    });
                }
            }, 'id');

        return $deleted;
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
