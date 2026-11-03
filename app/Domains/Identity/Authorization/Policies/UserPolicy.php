<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authorization\Policies;

use App\Core\Foundation\Policies\BasePolicy;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;

final class UserPolicy extends BasePolicy
{
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
        Cooperative $cooperative,
    ): bool {
        return $actor->hasRole(self::ROLE_SUPER_ADMIN_ROOT)
            && $this->hasPermission($actor, 'users.assign_super_admin')
            && ! $target->trashed()
            && $this->targetHasActiveRole($target, self::ROLE_SUPER_ADMIN)
            && $target->status === UserStatus::Active
            && ! $cooperative->trashed();
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
        Cooperative $cooperative,
    ): bool {
        return $actor->hasRole(self::ROLE_SUPER_ADMIN_ROOT)
            && $this->hasPermission($actor, 'users.unassign_super_admin')
            && ! $target->trashed()
            && $this->targetHasActiveRole($target, self::ROLE_SUPER_ADMIN)
            && ! $cooperative->trashed();
    }

    public function create(User $actor): bool
    {
        return $actor->hasAnyRole([
            self::ROLE_SUPER_ADMIN_ROOT,
            self::ROLE_SUPER_ADMIN,
        ]) && $this->hasPermission($actor, 'users.create');
    }

    public function activate(User $actor, User $target): bool
    {
        if (! $this->hasPermission($actor, 'users.activate') || $target->trashed()) {
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
            && $target->status !== UserStatus::Archived;
    }

    public function restore(User $actor, User $target): bool
    {
        return $actor->hasRole(self::ROLE_SUPER_ADMIN_ROOT)
            && $this->hasPermission($actor, 'users.restore')
            && ! $target->trashed()
            && $target->status === UserStatus::Archived;
    }

    public function delete(User $actor, User $target): bool
    {
        return $actor->hasRole(self::ROLE_SUPER_ADMIN_ROOT)
            && $this->hasPermission($actor, 'users.delete')
            && ! $target->trashed();
    }

    public function restoreDeleted(User $actor, User $target): bool
    {
        return $actor->hasRole(self::ROLE_SUPER_ADMIN_ROOT)
            && $this->hasPermission($actor, 'users.restore_deleted')
            && $target->trashed();
    }

    private function canChangeActiveStatus(
        User $actor,
        User $target,
        string $permission,
    ): bool {
        if (! $this->hasPermission($actor, $permission)
            || $target->trashed()
            || $target->status !== UserStatus::Active) {
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
                && $actor->cooperatives()
                    ->whereKey($target->cooperative_id)
                    ->exists();
        }

        if ($actor->hasRole(self::ROLE_COOPERATIVE_ADMIN)) {
            return $actor->cooperative_id !== null
                && (string) $actor->cooperative_id
                    === (string) $target->cooperative_id;
        }

        return false;
    }

    private function targetHasActiveRole(User $target, string $roleSlug): bool
    {
        return $target->roles()
            ->where('slug', $roleSlug)
            ->where('is_active', true)
            ->exists();
    }

    private function hasPermission(User $user, string $permission): bool
    {
        return $user->isAuthorized() && $user->hasPermission($permission);
    }
}
