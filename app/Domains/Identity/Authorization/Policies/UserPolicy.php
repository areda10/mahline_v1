<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authorization\Policies;

use App\Core\Foundation\Policies\BasePolicy;
use App\Domains\Cooperatives\Contracts\CooperativeIdentityReference;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use App\Shared\Contracts\SuperAdminCooperativeAssignmentReader;

final class UserPolicy extends BasePolicy
{
    public function __construct(
        private readonly CooperativeIdentityReference $cooperatives,
        private readonly SuperAdminCooperativeAssignmentReader $assignments,
    ) {}

    private const ROLE_SUPER_ADMIN_ROOT = 'super_admin_root';

    private const ROLE_SUPER_ADMIN = 'super_admin';

    private const ROLE_COOPERATIVE_ADMIN = 'cooperative_admin';

    public function viewAny(User $actor): bool
    {
        return $actor->hasAnyRole([
            self::ROLE_SUPER_ADMIN_ROOT,
            self::ROLE_SUPER_ADMIN,
            self::ROLE_COOPERATIVE_ADMIN,
        ]) && $this->hasPermission($actor, 'users.view');
    }

    public function view(User $actor, User $target): bool
    {
        return ! $target->trashed()
            && $this->canAccessUser($actor, $target)
            && ! (
                $actor->hasRole(self::ROLE_COOPERATIVE_ADMIN)
                && $this->targetHasActiveRole($target, self::ROLE_COOPERATIVE_ADMIN)
            )
            && $this->hasPermission($actor, 'users.view');
    }

    /**
     * View the cooperative assignments of a super administrator.
     *
     * A super administrator may inspect only their own assignments.
     * The platform root may inspect assignments for every super administrator.
     */
    public function viewAssignments(User $actor, User $target): bool
    {
        if ($target->trashed() || ! $this->targetHasActiveRole($target, self::ROLE_SUPER_ADMIN)) {
            return false;
        }

        return $this->hasPermission($actor, 'users.view')
            && (
                $actor->hasRole(self::ROLE_SUPER_ADMIN_ROOT)
                || (
                    $actor->hasRole(self::ROLE_SUPER_ADMIN)
                    && $actor->is($target)
                )
            );
    }

    /**
     * Assign an active super administrator to a cooperative.
     */
    public function assignCooperative(
        User $actor,
        User $target,
        string $cooperativeId,
    ): bool {
        return $actor->hasRole(self::ROLE_SUPER_ADMIN_ROOT)
            && $this->hasPermission($actor, 'users.assign_super_admin')
            && ! $target->trashed()
            && $this->targetHasActiveRole($target, self::ROLE_SUPER_ADMIN)
            && $target->isAuthorized()
            && $this->cooperatives->existsNotDeleted($cooperativeId);
    }

    /**
     * Remove a super administrator's cooperative assignment.
     *
     * Inactive or suspended super administrators remain eligible for
     * unassignment so that stale access can always be removed.
     */
    public function unassignCooperative(
        User $actor,
        User $target,
        string $cooperativeId,
    ): bool {
        return $actor->hasRole(self::ROLE_SUPER_ADMIN_ROOT)
            && $this->hasPermission($actor, 'users.unassign_super_admin')
            && ! $target->trashed()
            && $this->targetHasActiveRole($target, self::ROLE_SUPER_ADMIN)
            && $this->cooperatives->existsNotDeleted($cooperativeId);
    }

    public function update(User $actor, User $target): bool
    {
        if ($target->trashed()
            || $this->targetHasActiveRole($target, self::ROLE_SUPER_ADMIN_ROOT)
            || ! $this->hasPermission($actor, 'users.update')
            || ! $this->canAccessUser($actor, $target)) {
            return false;
        }

        if ($actor->hasRole(self::ROLE_SUPER_ADMIN_ROOT)) {
            return $this->targetHasAnyActiveRole($target, [self::ROLE_SUPER_ADMIN, self::ROLE_COOPERATIVE_ADMIN, 'user']);
        }

        if ($actor->hasRole(self::ROLE_SUPER_ADMIN)) {
            return $this->targetHasActiveRole($target, 'user');
        }

        if ($actor->hasRole(self::ROLE_COOPERATIVE_ADMIN)) {
            return $this->targetHasActiveRole($target, 'user');
        }

        return false;
    }

    public function create(User $actor): bool
    {
        if (! $this->hasPermission($actor, 'users.create')) {
            return false;
        }

        if ($actor->hasRole(self::ROLE_SUPER_ADMIN_ROOT)) {
            return true;
        }

        return false;
    }

    public function resendInvitation(User $actor, User $target): bool
    {
        if (! $this->hasPermission($actor, 'users.invite')
            || $target->trashed()
            || $target->email_verified_at !== null
            || ! $this->isInternalManagedTarget($target)) {
            return false;
        }

        if ($target->status === UserStatus::Active) {
            return $actor->hasRole(self::ROLE_SUPER_ADMIN_ROOT)
                || ($this->targetHasActiveRole($target, 'user')
                    && $this->canAccessUser($actor, $target)
                    && ($actor->hasRole(self::ROLE_SUPER_ADMIN) || $actor->hasRole(self::ROLE_COOPERATIVE_ADMIN)));
        }

        if ($target->status !== UserStatus::Pending) {
            return false;
        }

        return $actor->hasRole(self::ROLE_SUPER_ADMIN_ROOT)
            || ($this->targetHasActiveRole($target, 'user')
                && $this->canAccessUser($actor, $target)
                && ($actor->hasRole(self::ROLE_SUPER_ADMIN) || $actor->hasRole(self::ROLE_COOPERATIVE_ADMIN)));
    }

    /**
     * Send a password-reset link to an account managed by this actor.
     * Delegated administrators are restricted to ordinary users in scope.
     */
    public function sendPasswordReset(User $actor, User $target): bool
    {
        if (! $this->hasPermission($actor, 'users.invite')
            || $target->trashed()
            || $target->status !== UserStatus::Active
            || ! $this->isInternalManagedTarget($target)) {
            return false;
        }

        if ($actor->hasRole(self::ROLE_SUPER_ADMIN_ROOT)) {
            return true;
        }

        return $this->targetHasActiveRole($target, 'user')
            && $this->canAccessUser($actor, $target)
            && ($actor->hasRole(self::ROLE_SUPER_ADMIN)
                || $actor->hasRole(self::ROLE_COOPERATIVE_ADMIN));
    }

    public function activate(User $actor, User $target): bool
    {
        if (! $this->hasPermission($actor, 'users.activate') || $target->trashed() || ! $this->isInternalManagedTarget($target)) {
            return false;
        }

        if ($actor->hasRole(self::ROLE_SUPER_ADMIN_ROOT)) {
            return in_array($target->status, [
                UserStatus::Pending,
                UserStatus::Inactive,
                UserStatus::Suspended,
            ], true);
        }

        // Pending and inactive accounts may only be activated by the root.
        return false;
    }

    public function deactivate(User $actor, User $target): bool
    {
        return $this->canChangeActiveStatus($actor, $target, 'users.deactivate');
    }

    public function suspend(User $actor, User $target): bool
    {
        return $this->canChangeActiveStatus($actor, $target, 'users.suspend');
    }

    public function archive(User $actor, User $target): bool
    {
        return $actor->hasRole(self::ROLE_SUPER_ADMIN_ROOT)
            && $this->hasPermission($actor, 'users.archive')
            && ! $target->trashed()
            && $this->isInternalManagedTarget($target)
            && $target->status !== UserStatus::Archived;
    }

    public function restore(User $actor, User $target): bool
    {
        return $actor->hasRole(self::ROLE_SUPER_ADMIN_ROOT)
            && $this->hasPermission($actor, 'users.restore')
            && ! $target->trashed()
            && $this->isInternalManagedTarget($target)
            && $target->status === UserStatus::Archived;
    }

    public function delete(User $actor, User $target): bool
    {
        return $actor->hasRole(self::ROLE_SUPER_ADMIN_ROOT)
            && $this->hasPermission($actor, 'users.delete')
            && ! $target->trashed()
            && $this->isInternalManagedTarget($target);
    }

    public function restoreDeleted(User $actor, User $target): bool
    {
        return $actor->hasRole(self::ROLE_SUPER_ADMIN_ROOT)
            && $this->hasPermission($actor, 'users.restore_deleted')
            && $target->trashed()
            && $this->isInternalManagedTarget($target);
    }

    private function canChangeActiveStatus(
        User $actor,
        User $target,
        string $permission,
    ): bool {
        if (! $this->hasPermission($actor, $permission)
            || $target->trashed()
            || $target->status !== UserStatus::Active
            || ! $this->isInternalManagedTarget($target)) {
            return false;
        }

        return $actor->hasRole(self::ROLE_SUPER_ADMIN_ROOT)
            || $this->canManageStatus($actor, $target);
    }

    private function canManageStatus(User $actor, User $target): bool
    {
        return ! $actor->is($target)
            && $actor->hasRole(self::ROLE_SUPER_ADMIN)
            && $this->canAccessUser($actor, $target);
    }

    private function canAccessUser(User $actor, User $target): bool
    {
        if (! $actor->isAuthorized()) {
            return false;
        }

        if ($actor->hasRole(self::ROLE_SUPER_ADMIN_ROOT)) {
            return true;
        }

        if ($actor->hasRole(self::ROLE_SUPER_ADMIN)) {
            return $target->cooperative_id !== null
                && $this->assignments->isAssigned((string) $actor->getKey(), (string) $target->cooperative_id);
        }

        if ($actor->hasRole(self::ROLE_COOPERATIVE_ADMIN)) {
            return $actor->cooperative_id !== null
                && (string) $actor->cooperative_id
                    === (string) $target->cooperative_id;
        }

        return false;
    }

    private function isInternalManagedTarget(User $target): bool
    {
        // Role classification must remain independent from account status.
        // hasAnyRole() returns false for inactive, suspended or archived users.
        // A managed account must have an active operational role. This query
        // deliberately ignores User.status: suspended/archived accounts keep
        // their identity classification for root lifecycle operations.
        return $this->targetHasAnyActiveRole($target, [
            self::ROLE_SUPER_ADMIN,
            self::ROLE_COOPERATIVE_ADMIN,
            'user',
        ]);
    }

    private function targetHasActiveRole(User $target, string $roleSlug): bool
    {
        return $target->roles()
            ->where('slug', $roleSlug)
            ->where('is_active', true)
            ->exists();
    }

    /** @param array<int, string> $roleSlugs */
    private function targetHasAnyActiveRole(User $target, array $roleSlugs): bool
    {
        return $target->roles()
            ->whereIn('slug', $roleSlugs)
            ->where('roles.is_active', true)
            ->exists();
    }

    private function hasPermission(User $user, string $permission): bool
    {
        return $user->isAuthorized() && $user->hasPermission($permission);
    }
}
