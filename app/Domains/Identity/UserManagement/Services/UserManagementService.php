<?php

declare(strict_types=1);

namespace App\Domains\Identity\UserManagement\Services;

use App\Domains\Audit\Contracts\AuditWriter;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authentication\Services\ApiTokenService;
use App\Domains\Identity\Authentication\Services\SessionService;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Password\Services\PasswordHistoryService;
use App\Domains\Identity\UserManagement\Contracts\SuperAdminCooperativeAssignmentService;
use App\Domains\Identity\UserManagement\Models\UserInvitation;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Localization\Services\AddressService;
use App\Shared\DTOs\AuditEntryDTO;
use DomainException;
use Illuminate\Support\Facades\DB;

final class UserManagementService implements SuperAdminCooperativeAssignmentService
{
    private const ROLE_SUPER_ADMIN_ROOT = 'super_admin_root';

    private const ROLE_SUPER_ADMIN = 'super_admin';

    private const ROLE_COOPERATIVE_ADMIN = 'cooperative_admin';

    private const ROLE_USER = 'user';

    public function __construct(
        private readonly AuditWriter $audit,
        private readonly SessionService $sessions,
        private readonly ApiTokenService $apiTokens,
        private readonly PasswordHistoryService $passwordHistory,
        private readonly AddressService $addresses,
    ) {}

    /**
     * Update a normal user account and its single operational role.
     * Root accounts are managed outside the ordinary user CRUD.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateUserAccount(
        User $actor,
        User $target,
        array $attributes,
        string $roleSlug,
    ): User {
        $this->assertActorIsActive($actor);

        if ($roleSlug === self::ROLE_SUPER_ADMIN_ROOT) {
            throw new DomainException('The super admin root role cannot be assigned through user management.');
        }

        $role = $this->findRole($roleSlug);

        return DB::transaction(function () use (
            $actor,
            $target,
            $attributes,
            $role,
            $roleSlug,
        ): User {
            $lockedTarget = User::query()
                ->whereKey($target->getKey())
                ->lockForUpdate()
                ->first();

            if ($lockedTarget === null
                || $lockedTarget->roles()->where('slug', self::ROLE_SUPER_ADMIN_ROOT)->exists()) {
                throw new DomainException('Root accounts cannot be modified through ordinary user management.');
            }

            $currentRoleSlugs = $lockedTarget->roles()->pluck('slug')->all();
            if (count($currentRoleSlugs) !== 1) {
                throw new DomainException('The target account must have exactly one operational role.');
            }

            $currentRole = $currentRoleSlugs[0];
            $isRoot = $actor->hasRole(self::ROLE_SUPER_ADMIN_ROOT);

            if (! $isRoot) {
                if ($roleSlug !== $currentRole) {
                    throw new DomainException('Only the super admin root can change an account role.');
                }

                $this->assertCanUpdateTarget($actor, $lockedTarget, $currentRole);

                if (array_key_exists('cooperative_id', $attributes)
                    && (string) $attributes['cooperative_id'] !== (string) $lockedTarget->cooperative_id) {
                    throw new DomainException('Only the super admin root can change a cooperative assignment.');
                }

                $cooperativeId = $lockedTarget->cooperative_id;
            } else {
                $cooperativeId = $roleSlug === self::ROLE_SUPER_ADMIN
                    ? null
                    : ($attributes['cooperative_id'] ?? null);

                if ($roleSlug !== self::ROLE_SUPER_ADMIN) {
                    $this->assertCooperativeExists($cooperativeId);
                }
            }

            $emailChanged = $lockedTarget->email !== $attributes['email'];
            $roleChanged = $isRoot && $roleSlug !== $currentRole;
            $cooperativeAssignmentChanged = (string) $lockedTarget->cooperative_id
                !== (string) $cooperativeId;
            $accessScopeChanged = $roleChanged || $cooperativeAssignmentChanged;

            $oldValues = [
                'first_name' => $lockedTarget->first_name,
                'last_name' => $lockedTarget->last_name,
                'status' => $lockedTarget->status->value,
                'email' => $lockedTarget->email,
                'email_verified_at' => $lockedTarget->email_verified_at?->toISOString(),
                'telephone' => $lockedTarget->telephone,
                'cooperative_id' => $lockedTarget->cooperative_id,
                'roles' => $currentRoleSlugs,
            ];

            $lockedTarget->fill([
                'first_name' => $attributes['first_name'],
                'last_name' => $attributes['last_name'],
                'display_name' => trim($attributes['first_name'].' '.$attributes['last_name']),
                'email' => $attributes['email'],
                'telephone' => $attributes['telephone'],
                'cooperative_id' => $cooperativeId,
            ]);

            if ($emailChanged) {
                $lockedTarget->email_verified_at = null;
                $lockedTarget->status = UserStatus::Pending;
                $this->revokePendingInvitations($lockedTarget);
            }

            $lockedTarget->save();

            if ($isRoot) {
                $lockedTarget->roles()->sync([$role->getKey()]);

                if ($roleSlug !== self::ROLE_SUPER_ADMIN) {
                    $lockedTarget->cooperatives()->detach();
                }
            }

            $newValues = [
                'first_name' => $lockedTarget->first_name,
                'last_name' => $lockedTarget->last_name,
                'status' => $lockedTarget->status->value,
                'email' => $lockedTarget->email,
                'email_verified_at' => $lockedTarget->email_verified_at?->toISOString(),
                'telephone' => $lockedTarget->telephone,
                'cooperative_id' => $lockedTarget->cooperative_id,
                'roles' => $isRoot ? [$roleSlug] : $currentRoleSlugs,
            ];

            $this->recordLifecycleAudit(
                actor: $actor,
                target: $lockedTarget,
                eventKey: 'identity.user.updated',
                action: 'updated',
                oldValues: $oldValues,
                newValues: $newValues,
                metadata: ['profile_scope' => $isRoot
                    ? 'root'
                    : ($actor->hasRole(self::ROLE_SUPER_ADMIN)
                        ? 'assigned_cooperative'
                        : 'own_cooperative')],
            );

            if ($emailChanged || $accessScopeChanged) {
                $this->revokeAccessCredentials(
                    actor: $actor,
                    target: $lockedTarget,
                    reason: $emailChanged
                        ? 'user_email_changed'
                        : ($roleChanged ? 'user_role_changed' : 'user_cooperative_assignment_changed'),
                );
            }

            return $lockedTarget->fresh();
        });
    }

    public function createUser(
        User $actor,
        array $attributes,
        string $roleSlug,
        UserStatus $initialStatus = UserStatus::Pending,
    ): User {
        if ($initialStatus !== UserStatus::Pending) {
            throw new DomainException('A newly created user must start as pending.');
        }

        $this->assertActorIsActive($actor);
        $this->assertSuperAdminRoot($actor);

        if ($roleSlug === self::ROLE_SUPER_ADMIN_ROOT) {
            throw new DomainException('The super admin root role cannot be assigned through user management.');
        }

        $role = $this->findRole($roleSlug);

        if ($roleSlug === self::ROLE_SUPER_ADMIN) {
            $attributes['cooperative_id'] = null;
        } else {
            $cooperativeId = $attributes['cooperative_id'] ?? null;
            $this->assertCooperativeExists($cooperativeId);
        }

        return DB::transaction(function () use (
            $attributes,
            $role,
            $actor,
            $roleSlug,
            $initialStatus,
        ): User {
            if ($roleSlug !== self::ROLE_SUPER_ADMIN) {
                Cooperative::query()
                    ->whereKey($attributes['cooperative_id'])
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $userAttributes = array_merge($attributes, [
                'display_name' => trim(
                    $attributes['first_name'].' '.$attributes['last_name'],
                ),
                // Secret aléatoire provisoire, jamais communiqué à l'administrateur.
                'password' => bin2hex(random_bytes(32)),
                // 'status' => UserStatus::Pending,
                'status' => UserStatus::Pending,
                'email_verified_at' => null,
                'must_change_password' => false,
            ]);

            $user = User::query()->create($userAttributes);

            $user->roles()->attach($role->getKey());

            $this->audit->record(new AuditEntryDTO(
                eventKey: 'identity.user.created',
                action: 'created',
                actorType: User::class,
                actorId: (string) $actor->getKey(),
                cooperativeId: $user->cooperative_id
                    ? (string) $user->cooperative_id
                    : null,
                subjectType: User::class,
                subjectId: (string) $user->getKey(),
                newValues: [
                    'role' => $roleSlug,
                    'cooperative_id' => $user->cooperative_id,
                    // 'status' => UserStatus::Pending->value,
                    'status' => $initialStatus->value,
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

        DB::transaction(function () use (
            $actor,
            $superAdmin,
            $cooperative,
        ): void {
            $lockedSuperAdmin = User::query()
                ->whereKey($superAdmin->getKey())
                ->lockForUpdate()
                ->first();

            if ($lockedSuperAdmin === null) {
                throw new DomainException(
                    'The super admin does not exist or is deleted.',
                );
            }

            // Revalidation après acquisition du verrou.
            $this->assertSuperAdminTarget($lockedSuperAdmin);

            // Lock the cooperative so concurrent assignment attempts cannot
            // attach two different super administrators to the same cooperative.
            $lockedCooperative = Cooperative::query()
                ->whereKey($cooperative->getKey())
                ->lockForUpdate()
                ->first();

            if ($lockedCooperative === null) {
                throw new DomainException('The cooperative does not exist or is deleted.');
            }

            $alreadyAssigned = $lockedSuperAdmin->cooperatives()
                ->whereKey($lockedCooperative->getKey())
                ->exists();

            if ($alreadyAssigned) {
                return;
            }

            $assignedToAnotherSuperAdmin = $lockedCooperative->superAdmins()
                ->where('users.id', '<>', $lockedSuperAdmin->getKey())
                ->exists();

            if ($assignedToAnotherSuperAdmin) {
                throw new DomainException('A cooperative can be assigned to only one super admin.');
            }

            $lockedSuperAdmin->cooperatives()->attach(
                $cooperative->getKey(),
            );

            // L'événement d'audit appartient à la même transaction.
            $this->apiTokens->revokeForUser(
                user: $lockedSuperAdmin,
                actor: $actor,
                reason: 'super_admin_cooperative_assignment_changed',
                eventKey: 'identity.authentication.api_token_revoked',
                action: 'api_token_revoked',
            );

            $this->audit->record(new AuditEntryDTO(
                eventKey: 'identity.super_admin.cooperative_assigned',
                action: 'assigned',
                actorType: User::class,
                actorId: (string) $actor->getKey(),
                cooperativeId: (string) $cooperative->getKey(),
                subjectType: User::class,
                subjectId: (string) $lockedSuperAdmin->getKey(),
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
            $lockedSuperAdmin = User::query()
                ->whereKey($superAdmin->getKey())
                ->lockForUpdate()
                ->first();

            if ($lockedSuperAdmin === null) {
                throw new DomainException(
                    'The super admin does not exist or is deleted.',
                );
            }

            // Le rôle est obligatoire, mais le compte peut être inactif
            // ou suspendu : la désaffectation reste autorisée.
            $this->assertSuperAdminRoleTarget($lockedSuperAdmin);

            $isAssigned = $lockedSuperAdmin->cooperatives()
                ->whereKey($cooperative->getKey())
                ->exists();

            if (! $isAssigned) {
                return;
            }

            $lockedSuperAdmin->cooperatives()->detach(
                $cooperative->getKey(),
            );

            // L'événement d'audit appartient à la même transaction.
            $this->apiTokens->revokeForUser(
                user: $lockedSuperAdmin,
                actor: $actor,
                reason: 'super_admin_cooperative_assignment_changed',
                eventKey: 'identity.authentication.api_token_revoked',
                action: 'api_token_revoked',
            );

            $this->audit->record(new AuditEntryDTO(
                eventKey: 'identity.super_admin.cooperative_unassigned',
                action: 'unassigned',
                actorType: User::class,
                actorId: (string) $actor->getKey(),
                cooperativeId: (string) $cooperative->getKey(),
                subjectType: User::class,
                subjectId: (string) $lockedSuperAdmin->getKey(),
                oldValues: [
                    'cooperative_id' => (string) $cooperative->getKey(),
                ],
            ));
        });
    }

    /**
     * Activate a pending, inactive, or suspended account.
     * This lifecycle transition is reserved to the root administrator.
     */
    public function activateUser(User $actor, User $target): User
    {
        $this->assertActorIsActive($actor);
        $this->assertSuperAdminRoot($actor);

        $rootCanActivateFrom = [
            UserStatus::Pending,
            UserStatus::Inactive,
            UserStatus::Suspended,
        ];

        return DB::transaction(function () use ($actor, $target, $rootCanActivateFrom): User {
            $lockedTarget = User::query()->whereKey($target->getKey())->lockForUpdate()->first();

            if ($lockedTarget === null) {
                throw new DomainException('The user does not exist or is deleted.');
            }

            $this->assertTargetIsNotRoot($lockedTarget);

            if (! in_array($lockedTarget->status, $rootCanActivateFrom, true)) {
                throw new DomainException('This account cannot be activated from its current status.');
            }

            $oldStatus = $lockedTarget->status;
            $oldEmailVerifiedAt = $lockedTarget->email_verified_at?->toISOString();
            $lockedTarget->status = UserStatus::Active;
            $lockedTarget->email_verified_at = null;
            $lockedTarget->must_change_password = false;
            $lockedTarget->save();

            $this->recordLifecycleAudit(
                actor: $actor,
                target: $lockedTarget,
                eventKey: 'identity.user.activated_for_email_verification',
                action: 'activated_for_email_verification',
                oldValues: ['status' => $oldStatus->value, 'email_verified_at' => $oldEmailVerifiedAt],
                newValues: ['status' => UserStatus::Active->value, 'email_verified_at' => null],
            );

            $this->revokePendingInvitations($lockedTarget);
            $this->revokeAccessCredentials($actor, $lockedTarget, 'user_reactivated');

            return $lockedTarget->fresh();
        });
    }

    public function activateAndVerifyUser(User $actor, User $target): User
    {
        $this->assertActorIsActive($actor);
        $this->assertSuperAdminRoot($actor);

        return DB::transaction(function () use ($actor, $target): User {
            $lockedTarget = User::query()->whereKey($target->getKey())->lockForUpdate()->first();

            if ($lockedTarget === null) {
                throw new DomainException('The user does not exist or is deleted.');
            }

            $this->assertTargetIsNotRoot($lockedTarget);

            if (! in_array($lockedTarget->status, [
                UserStatus::Pending, UserStatus::Inactive, UserStatus::Suspended,
            ], true)) {
                throw new DomainException('This account cannot be activated from its current status.');
            }

            $oldStatus = $lockedTarget->status;
            $oldVerifiedAt = $lockedTarget->email_verified_at?->toISOString();

            // Preserve the existing credential before replacing it with the
            // temporary root-activation password. The forced change must not
            // allow the user to immediately reuse the pre-activation password.
            $this->passwordHistory->archiveCurrentPassword($lockedTarget);

            $lockedTarget->status = UserStatus::Active;
            $lockedTarget->email_verified_at = now();
            $lockedTarget->password = 'MahlineMahline';
            $lockedTarget->must_change_password = true;
            $lockedTarget->save();

            $this->recordLifecycleAudit(
                actor: $actor,
                target: $lockedTarget,
                eventKey: 'identity.user.activated_with_temporary_password',
                action: 'activated_with_temporary_password',
                oldValues: ['status' => $oldStatus->value, 'email_verified_at' => $oldVerifiedAt],
                newValues: ['status' => UserStatus::Active->value, 'email_verified_at' => $lockedTarget->email_verified_at->toISOString()],
                metadata: ['must_change_password' => true],
            );

            $this->revokePendingInvitations($lockedTarget);
            $this->revokeAccessCredentials($actor, $lockedTarget, 'user_reactivated_with_temporary_password');

            return $lockedTarget->fresh();
        });
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
            newStatus: UserStatus::Pending,
            action: 'restored_pending_invitation',
            allowedRootSourceStatuses: [UserStatus::Archived],
            allowedSuperAdminSourceStatuses: [],
            revokeReason: 'user_restored',
            rootOnly: true,
            resetEmailVerification: true,
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

            if ($lockedTarget->roles()->where('slug', self::ROLE_SUPER_ADMIN_ROOT)->exists()) {
                throw new DomainException('The super admin root account cannot be deleted.');
            }

            $this->recordLifecycleAudit(
                actor: $actor,
                target: $lockedTarget,
                eventKey: 'identity.user.deleted',
                action: 'deleted',
                oldValues: ['deleted_at' => null],
                newValues: ['deleted_at' => now()->toISOString()],
            );

            $this->revokePendingInvitations($lockedTarget);
            $this->addresses->softDeleteUserAddresses($lockedTarget, (string) $actor->getKey());

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
     * The account returns to pending and must complete a fresh activation flow.
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

            $this->assertTargetIsNotRoot($lockedTarget);

            $oldDeletedAt = $lockedTarget->deleted_at?->toISOString();
            $oldStatus = $lockedTarget->status->value;
            $oldEmailVerifiedAt = $lockedTarget->email_verified_at?->toISOString();

            $lockedTarget->restore();
            $this->addresses->restoreLatestUserAddresses($lockedTarget, (string) $actor->getKey());
            $lockedTarget->status = UserStatus::Pending;
            $lockedTarget->email_verified_at = null;
            $lockedTarget->must_change_password = false;
            $lockedTarget->save();

            $this->recordLifecycleAudit(
                actor: $actor,
                target: $lockedTarget,
                eventKey: 'identity.user.restored',
                action: 'restored',
                oldValues: [
                    'deleted_at' => $oldDeletedAt,
                    'status' => $oldStatus,
                    'email_verified_at' => $oldEmailVerifiedAt,
                ],
                newValues: [
                    'deleted_at' => null,
                    'status' => UserStatus::Pending->value,
                    'email_verified_at' => null,
                    'must_change_password' => false,
                ],
            );

            $this->revokePendingInvitations($lockedTarget);
            $this->revokeAccessCredentials(
                actor: $actor,
                target: $lockedTarget,
                reason: 'user_restored',
            );

            return $lockedTarget->fresh();
        });
    }

    /**
     * Apply one validated status transition. The status and its audit event
     * are persisted before any Web session or API token is revoked.
     *
     * @param  list<UserStatus>  $allowedRootSourceStatuses
     * @param  list<UserStatus>  $allowedSuperAdminSourceStatuses
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
        bool $resetEmailVerification = false,
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
            $resetEmailVerification,
        ): User {
            $lockedTarget = User::query()
                ->whereKey($target->getKey())
                ->lockForUpdate()
                ->first();

            if ($lockedTarget === null) {
                throw new DomainException('The user does not exist or is deleted.');
            }

            $this->assertTargetIsNotRoot($lockedTarget);

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
            $oldEmailVerifiedAt = $lockedTarget->email_verified_at?->toISOString();
            $oldMustChangePassword = $lockedTarget->must_change_password;
            $lockedTarget->status = $newStatus;

            if ($resetEmailVerification) {
                $lockedTarget->email_verified_at = null;
                $lockedTarget->must_change_password = false;
            }

            $lockedTarget->save();

            $oldValues = ['status' => $oldStatus->value];
            $newValues = ['status' => $newStatus->value];

            if ($resetEmailVerification) {
                $oldValues['email_verified_at'] = $oldEmailVerifiedAt;
                $oldValues['must_change_password'] = $oldMustChangePassword;
                $newValues['email_verified_at'] = null;
                $newValues['must_change_password'] = false;
            }

            $this->recordLifecycleAudit(
                actor: $actor,
                target: $lockedTarget,
                eventKey: 'identity.user.status_changed',
                action: 'status_changed',
                oldValues: $oldValues,
                newValues: $newValues,
                metadata: ['operation' => $action],
            );

            if ($revokeReason !== null) {
                $this->revokePendingInvitations($lockedTarget);

                $this->revokeAccessCredentials(
                    actor: $actor,
                    target: $lockedTarget,
                    reason: $revokeReason,
                );
            }

            return $lockedTarget->fresh();
        });
    }

    private function assertCanUpdateTarget(
        User $actor,
        User $target,
        string $targetRole,
    ): void {
        if ($actor->hasRole(self::ROLE_SUPER_ADMIN)) {
            if ($targetRole !== self::ROLE_USER
                || $target->cooperative_id === null
                || ! $actor->cooperatives()->whereKey($target->cooperative_id)->exists()) {
                throw new DomainException('The super admin cannot update this account outside its assigned cooperative scope.');
            }

            return;
        }

        if ($actor->hasRole(self::ROLE_COOPERATIVE_ADMIN)) {
            if ($targetRole !== self::ROLE_USER
                || $actor->cooperative_id === null
                || (string) $actor->cooperative_id !== (string) $target->cooperative_id) {
                throw new DomainException('The cooperative admin can update only users in its own cooperative.');
            }

            return;
        }

        throw new DomainException('The actor is not authorized to update this account.');
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

    private function revokePendingInvitations(User $target): void
    {
        UserInvitation::query()
            ->where('user_id', $target->getKey())
            ->whereNull('used_at')
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => now(),
            ]);
    }

    private function revokeAccessCredentials(
        User $actor,
        User $target,
        string $reason,
    ): void {
        // SessionService writes one LoginHistory and one Audit event per session.
        $this->sessions->revokeForUser($target, $reason);

        $this->apiTokens->revokeForUser(
            user: $target,
            actor: $actor,
            reason: $reason,
            eventKey: 'identity.authentication.api_token_revoked',
            action: 'api_token_revoked',
        );
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

    private function assertTargetIsNotRoot(User $target): void
    {
        if ($target->roles()->where('slug', self::ROLE_SUPER_ADMIN_ROOT)->exists()) {
            throw new DomainException('The super admin root account status cannot be changed.');
        }
    }

    private function assertSuperAdminRoot(User $actor): void
    {
        if (! $actor->hasRole(self::ROLE_SUPER_ADMIN_ROOT)) {
            throw new DomainException(
                'Only the super admin root can perform this action.',
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
