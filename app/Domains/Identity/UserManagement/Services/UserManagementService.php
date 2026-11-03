<?php

declare(strict_types=1);

namespace App\Domains\Identity\UserManagement\Services;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Authentication\Models\PersonalAccessToken;
use App\Domains\Identity\Authentication\Services\SessionService;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use App\Shared\DTOs\AuditEntryDTO;
use DomainException;
use Illuminate\Support\Facades\DB;

final class UserManagementService
{
    private const ROLE_SUPER_ADMIN_ROOT = 'super_admin_root';

    private const ROLE_SUPER_ADMIN = 'super_admin';

    private const ROLE_COOPERATIVE_ADMIN = 'cooperative_admin';

    private const ROLE_USER = 'user';

    public function __construct(
        private readonly AuditService $audit,
        private readonly SessionService $sessions,
    ) {
    }

    public function createUser(
        User $actor,
        array $attributes,
        string $roleSlug,
    ): User {
        $this->assertActorIsActive($actor);

        $role = $this->findRole($roleSlug);

        $this->assertCanAssignRole($actor, $roleSlug);

        if ($roleSlug === self::ROLE_SUPER_ADMIN) {
            $this->assertSuperAdminRoot($actor);

            // Un super_admin n'appartient pas directement
            // à une coopérative.
            $attributes['cooperative_id'] = null;
        } else {
            $cooperativeId = $attributes['cooperative_id'] ?? null;

            $this->assertCanCreateUser($actor, $cooperativeId);
            $this->assertCooperativeExists($cooperativeId);
        }

        return DB::transaction(function () use (
            $attributes,
            $role,
            $actor,
            $roleSlug,
        ): User {
            $user = User::query()->create($attributes);

            $user->roles()->attach($role->getKey());

            $this->audit->record(new AuditEntryDTO(
                eventKey: 'identity.user.created',
                action: 'created',
                actorType: User::class,
                actorId: (string) $actor->getKey(),
                cooperativeId: isset($attributes['cooperative_id'])
                ? (string) $attributes['cooperative_id']
                : null,
                subjectType: User::class,
                subjectId: (string) $user->getKey(),
                newValues: [
                    'role' => $roleSlug,
                    'cooperative_id' => $attributes['cooperative_id'] ?? null,
                ],
            ));

            return $user;
        });
    }
    

    public function assignSuperAdminToCooperative(
        User $actor,
        User $superAdmin,
        Cooperative $cooperative,
    ): void {
        $this->assertActorIsActive($actor);
        $this->assertSuperAdminRoot($actor);
        $this->assertSuperAdminTarget($superAdmin);
        $this->assertCooperativeExists($cooperative->getKey());

        // DB::transaction(function () use (
        //     $superAdmin,
        //     $cooperative,
        // ): void {
        //     $superAdmin->cooperatives()->syncWithoutDetaching([
        //         $cooperative->getKey(),
        //     ]);
        // });
        DB::transaction(function () use (
            $actor,
            $superAdmin,
            $cooperative,
        ): void {
            $alreadyAssigned = $superAdmin->cooperatives()
                ->whereKey($cooperative->getKey())
                ->exists();

            if ($alreadyAssigned) {
                return;
            }

            $superAdmin->cooperatives()->attach(
                $cooperative->getKey(),
            );

            $this->audit->record(new AuditEntryDTO(
                eventKey: 'identity.super_admin.cooperative_assigned',
                action: 'assigned',
                actorType: User::class,
                actorId: (string) $actor->getKey(),
                cooperativeId: (string) $cooperative->getKey(),
                subjectType: User::class,
                subjectId: (string) $superAdmin->getKey(),
                newValues: [
                    'cooperative_id' => (string) $cooperative->getKey(),
                ],
            ));
        });
    }

    public function removeSuperAdminFromCooperative(
        User $actor,
        User $superAdmin,
        Cooperative $cooperative,
    ): void {
        $this->assertActorIsActive($actor);
        $this->assertSuperAdminRoot($actor);
        $this->assertSuperAdminRoleTarget($superAdmin);
        $this->assertCooperativeExists($cooperative->getKey());

        DB::transaction(function () use (
            $actor,
            $superAdmin,
            $cooperative,
        ): void {
            $isAssigned = $superAdmin->cooperatives()
                ->whereKey($cooperative->getKey())
                ->exists();

            if (! $isAssigned) {
                return;
            }

            $superAdmin->cooperatives()->detach(
                $cooperative->getKey(),
            );

            $this->audit->record(new AuditEntryDTO(
                eventKey: 'identity.super_admin.cooperative_unassigned',
                action: 'unassigned',
                actorType: User::class,
                actorId: (string) $actor->getKey(),
                cooperativeId: (string) $cooperative->getKey(),
                subjectType: User::class,
                subjectId: (string) $superAdmin->getKey(),
                oldValues: [
                    'cooperative_id' => (string) $cooperative->getKey(),
                ],
            ));
        });
    }

    /**
     * Activate a pending or inactive account.
     * Only the root administrator may reactivate a suspended account.
     */
    public function activateUser(User $actor, User $target): User
    {
        $this->assertActorIsActive($actor);

        $rootCanActivateFrom = [
            UserStatus::Pending,
            UserStatus::Inactive,
            UserStatus::Suspended,
        ];
        $superAdminCanActivateFrom = [
            UserStatus::Pending,
            UserStatus::Inactive,
        ];

        return $this->changeUserStatus(
            actor: $actor,
            target: $target,
            newStatus: UserStatus::Active,
            action: 'activated',
            allowedRootSourceStatuses: $rootCanActivateFrom,
            allowedSuperAdminSourceStatuses: $superAdminCanActivateFrom,
        );
    }

    public function deactivateUser(User $actor, User $target): User
    {
        $this->assertActorIsActive($actor);

        return $this->changeUserStatus(
            actor: $actor,
            target: $target,
            newStatus: UserStatus::Inactive,
            action: 'deactivated',
            allowedRootSourceStatuses: [UserStatus::Active],
            allowedSuperAdminSourceStatuses: [UserStatus::Active],
            revokeReason: 'user_deactivated',
        );
    }

    public function suspendUser(User $actor, User $target): User
    {
        $this->assertActorIsActive($actor);

        return $this->changeUserStatus(
            actor: $actor,
            target: $target,
            newStatus: UserStatus::Suspended,
            action: 'suspended',
            allowedRootSourceStatuses: [UserStatus::Active],
            allowedSuperAdminSourceStatuses: [UserStatus::Active],
            revokeReason: 'user_suspended',
        );
    }

    public function archiveUser(User $actor, User $target): User
    {
        $this->assertActorIsActive($actor);
        $this->assertSuperAdminRoot($actor);

        return $this->changeUserStatus(
            actor: $actor,
            target: $target,
            newStatus: UserStatus::Archived,
            action: 'archived',
            allowedRootSourceStatuses: [
                UserStatus::Pending,
                UserStatus::Active,
                UserStatus::Inactive,
                UserStatus::Suspended,
            ],
            allowedSuperAdminSourceStatuses: [],
            revokeReason: 'user_archived',
            rootOnly: true,
        );
    }

    public function restoreArchivedUser(User $actor, User $target): User
    {
        $this->assertActorIsActive($actor);
        $this->assertSuperAdminRoot($actor);

        return $this->changeUserStatus(
            actor: $actor,
            target: $target,
            newStatus: UserStatus::Active,
            action: 'restored',
            allowedRootSourceStatuses: [UserStatus::Archived],
            allowedSuperAdminSourceStatuses: [],
            rootOnly: true,
        );
    }

    /**
     * Soft-delete a user after recording the action and revoking credentials.
     * Historical session and token records remain available for audit.
     */
    public function deleteUser(User $actor, User $target): bool
    {
        $this->assertActorIsActive($actor);
        $this->assertSuperAdminRoot($actor);

        return DB::transaction(function () use ($actor, $target): bool {
            $lockedTarget = User::query()
                ->whereKey($target->getKey())
                ->lockForUpdate()
                ->first();

            if ($lockedTarget === null) {
                throw new DomainException('The user does not exist or is already deleted.');
            }

            $this->recordLifecycleAudit(
                actor: $actor,
                target: $lockedTarget,
                eventKey: 'identity.user.deleted',
                action: 'deleted',
                oldValues: ['deleted_at' => null],
                newValues: ['deleted_at' => now()->toISOString()],
            );

            $this->revokeAccessCredentials(
                actor: $actor,
                target: $lockedTarget,
                reason: 'user_deleted',
            );

            return (bool) $lockedTarget->delete();
        });
    }

    /**
     * Restore a soft-deleted user without restoring its old sessions or tokens.
     * The account status is deliberately preserved.
     */
    public function restoreDeletedUser(User $actor, User $target): User
    {
        $this->assertActorIsActive($actor);
        $this->assertSuperAdminRoot($actor);

        return DB::transaction(function () use ($actor, $target): User {
            $lockedTarget = User::withTrashed()
                ->whereKey($target->getKey())
                ->lockForUpdate()
                ->first();

            if ($lockedTarget === null || ! $lockedTarget->trashed()) {
                throw new DomainException('Only a soft-deleted user can be restored.');
            }

            $oldDeletedAt = $lockedTarget->deleted_at?->toISOString();

            $this->recordLifecycleAudit(
                actor: $actor,
                target: $lockedTarget,
                eventKey: 'identity.user.restored',
                action: 'restored',
                oldValues: ['deleted_at' => $oldDeletedAt],
                newValues: ['deleted_at' => null],
            );

            $lockedTarget->restore();

            return $lockedTarget->fresh();
        });
    }

    /**
     * Apply one validated status transition. The status and its audit event
     * are persisted before any Web session or API token is revoked.
     *
     * @param list<UserStatus> $allowedRootSourceStatuses
     * @param list<UserStatus> $allowedSuperAdminSourceStatuses
     */
    private function changeUserStatus(
        User $actor,
        User $target,
        UserStatus $newStatus,
        string $action,
        array $allowedRootSourceStatuses,
        array $allowedSuperAdminSourceStatuses,
        ?string $revokeReason = null,
        bool $rootOnly = false,
    ): User {
        return DB::transaction(function () use (
            $actor,
            $target,
            $newStatus,
            $action,
            $allowedRootSourceStatuses,
            $allowedSuperAdminSourceStatuses,
            $revokeReason,
            $rootOnly,
        ): User {
            $lockedTarget = User::query()
                ->whereKey($target->getKey())
                ->lockForUpdate()
                ->first();

            if ($lockedTarget === null) {
                throw new DomainException('The user does not exist or is deleted.');
            }

            $isRoot = $actor->hasRole(self::ROLE_SUPER_ADMIN_ROOT);

            if (! $isRoot && $rootOnly) {
                throw new DomainException('Only the super admin root can perform this action.');
            }

            if (! $isRoot) {
                $this->assertSuperAdminCanManageTarget($actor, $lockedTarget);
            }

            $allowedSourceStatuses = $isRoot
                ? $allowedRootSourceStatuses
                : $allowedSuperAdminSourceStatuses;

            if (! in_array($lockedTarget->status, $allowedSourceStatuses, true)) {
                throw new DomainException(sprintf(
                    'The user cannot transition from "%s" to "%s".',
                    $lockedTarget->status->value,
                    $newStatus->value,
                ));
            }

            $oldStatus = $lockedTarget->status;
            $lockedTarget->status = $newStatus;
            $lockedTarget->save();

            $this->recordLifecycleAudit(
                actor: $actor,
                target: $lockedTarget,
                eventKey: 'identity.user.status_changed',
                action: 'status_changed',
                oldValues: ['status' => $oldStatus->value],
                newValues: ['status' => $newStatus->value],
                metadata: ['operation' => $action],
            );

            if ($revokeReason !== null) {
                $this->revokeAccessCredentials(
                    actor: $actor,
                    target: $lockedTarget,
                    reason: $revokeReason,
                );
            }

            return $lockedTarget->fresh();
        });
    }

    private function assertSuperAdminCanManageTarget(
        User $actor,
        User $target,
    ): void {
        if (
            ! $actor->hasRole(self::ROLE_SUPER_ADMIN)
            || $actor->is($target)
            || $target->cooperative_id === null
            || ! $actor->cooperatives()
                ->whereKey($target->cooperative_id)
                ->exists()
        ) {
            throw new DomainException(
                'The super admin cannot manage this user.',
            );
        }
    }

    private function revokeAccessCredentials(
        User $actor,
        User $target,
        string $reason,
    ): void {
        // SessionService writes one LoginHistory and one Audit event per session.
        $this->sessions->revokeForUser($target, $reason);

        // The revoked_at column is part of the MAHLINE token lifecycle migration.
        $tokens = PersonalAccessToken::query()
            ->where('tokenable_type', $target->getMorphClass())
            ->where('tokenable_id', $target->getKey())
            ->whereNull('revoked_at')
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->lockForUpdate()
            ->get();

        foreach ($tokens as $token) {
            $revokedAt = now();
            $token->revoked_at = $revokedAt;
            $token->save();

            $this->audit->record(new AuditEntryDTO(
                eventKey: 'identity.authentication.api_token_revoked',
                action: 'api_token_revoked',
                actorType: User::class,
                actorId: (string) $actor->getKey(),
                cooperativeId: $target->cooperative_id
                    ? (string) $target->cooperative_id
                    : null,
                subjectType: PersonalAccessToken::class,
                subjectId: (string) $token->getKey(),
                oldValues: ['revoked_at' => null],
                newValues: ['revoked_at' => $revokedAt->toISOString()],
                metadata: [
                    'user_id' => (string) $target->getKey(),
                    'token_name' => $token->name,
                    'reason' => $reason,
                ],
            ));
        }
    }

    private function recordLifecycleAudit(
        User $actor,
        User $target,
        string $eventKey,
        string $action,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null,
    ): void {
        $this->audit->record(new AuditEntryDTO(
            eventKey: $eventKey,
            action: $action,
            actorType: User::class,
            actorId: (string) $actor->getKey(),
            cooperativeId: $target->cooperative_id
                ? (string) $target->cooperative_id
                : null,
            subjectType: User::class,
            subjectId: (string) $target->getKey(),
            oldValues: $oldValues,
            newValues: $newValues,
            metadata: $metadata,
        ));
    }

    private function assertSuperAdminRoleTarget(User $superAdmin): void
    {
        $hasSuperAdminRole = $superAdmin->roles()
            ->where('slug', self::ROLE_SUPER_ADMIN)
            ->exists();

        if (! $hasSuperAdminRole) {
            throw new DomainException(
                'The target user must have the super_admin role.',
            );
        }
    }

    private function assertActorIsActive(User $actor): void
    {
        if (! $actor->isAuthorized()) {
            throw new DomainException(
                'An inactive user cannot manage users or assignments.',
            );
        }
    }

    private function assertCanCreateUser(
        User $actor,
        string|int|null $cooperativeId,
    ): void {
        $this->assertCooperativeIdProvided($cooperativeId);

        if ($actor->hasRole(self::ROLE_SUPER_ADMIN_ROOT)) {
            return;
        }

        if ($actor->hasRole(self::ROLE_SUPER_ADMIN)) {
            if (! $actor->cooperatives()
                ->whereKey($cooperativeId)
                ->exists()) {
                throw new DomainException(
                    'The super admin is not assigned to this cooperative.',
                );
            }

            return;
        }

        throw new DomainException(
            'The user is not authorized to create users.',
        );
    }

    private function assertCanAssignRole(
        User $actor,
        string $roleSlug,
    ): void {
        $allowedRoles = match (true) {
            $actor->hasRole(self::ROLE_SUPER_ADMIN_ROOT) => [
                self::ROLE_SUPER_ADMIN,
                self::ROLE_COOPERATIVE_ADMIN,
                self::ROLE_USER,
            ],
            $actor->hasRole(self::ROLE_SUPER_ADMIN) => [
                self::ROLE_COOPERATIVE_ADMIN,
                self::ROLE_USER,
            ],
            default => [],
        };

        if (! in_array($roleSlug, $allowedRoles, true)) {
            throw new DomainException(
                sprintf(
                    'The actor is not authorized to assign the "%s" role.',
                    $roleSlug,
                ),
            );
        }
    }

    private function assertCooperativeIdProvided(
        string|int|null $cooperativeId,
    ): void {
        if ($cooperativeId === null || $cooperativeId === '') {
            throw new DomainException(
                'A cooperative is required for user creation.',
            );
        }
    }

    private function assertCooperativeExists(
        string|int|null $cooperativeId,
    ): void {
        $this->assertCooperativeIdProvided($cooperativeId);

        $exists = Cooperative::query()
            ->whereKey($cooperativeId)
            ->exists();

        if (! $exists) {
            throw new DomainException(
                'The cooperative does not exist or is no longer available.',
            );
        }
    }

    private function assertSuperAdminRoot(User $actor): void
    {
        if (! $actor->hasRole(self::ROLE_SUPER_ADMIN_ROOT)) {
            throw new DomainException(
                'Only the super admin root can manage super admin assignments.',
            );
        }
    }

    private function assertSuperAdminTarget(User $superAdmin): void
    {
        if (! $superAdmin->isAuthorized()) {
            throw new DomainException(
                'An inactive user cannot receive or retain a super admin assignment.',
            );
        }

        $this->assertSuperAdminRoleTarget($superAdmin);
    }

    private function findRole(string $roleSlug): Role
    {
        $role = Role::query()
            ->where('slug', $roleSlug)
            ->first();

        if ($role === null) {
            throw new DomainException(
                sprintf('Unknown role "%s".', $roleSlug),
            );
        }

        if (! $role->is_active) {
            throw new DomainException(
                sprintf('Role "%s" is inactive.', $roleSlug),
            );
        }

        return $role;
    }
}
