<?php

declare(strict_types=1);

namespace App\Domains\Identity\UserManagement\Services;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Users\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final class UserManagementService
{
    private const ROLE_SUPER_ADMIN_ROOT = 'super_admin_root';

    private const ROLE_SUPER_ADMIN = 'super_admin';

    private const ROLE_COOPERATIVE_ADMIN = 'cooperative_admin';

    private const ROLE_USER = 'user';

    public function createUser(
        User $actor,
        array $attributes,
        string $roleSlug,
    ): User {
        
        $role = $this->findRole($roleSlug);

        $this->assertCanAssignRole(
            $actor,
            $roleSlug,
        );

        if ($roleSlug === self::ROLE_SUPER_ADMIN) {
            $this->assertSuperAdminRoot($actor);
            $attributes['cooperative_id'] = null;
        } else {
            $this->assertCanCreateUser( 
                $actor, 
                $attributes['cooperative_id'] ?? null, 
            );
        }

        return DB::transaction(function () use (
            $attributes,
            $role,
        ): User {
            $user = User::create($attributes);

            $user->roles()->attach($role->id);

            return $user;
        });
    }

    public function assignSuperAdminToCooperative(
        User $actor,
        User $superAdmin,
        Cooperative $cooperative,
    ): void {
        $this->assertSuperAdminRoot($actor);
        $this->assertSuperAdminTarget($superAdmin);

        $superAdmin->cooperatives()->syncWithoutDetaching([
            $cooperative->id,
        ]);
    }

    public function removeSuperAdminFromCooperative(
        User $actor,
        User $superAdmin,
        Cooperative $cooperative,
    ): void {
        $this->assertSuperAdminRoot($actor);
        $this->assertSuperAdminTarget($superAdmin);

        $superAdmin->cooperatives()->detach($cooperative->id);
    }

    private function assertCanCreateUser(
        User $actor,
        string|int|null $cooperativeId,
    ): void {
        if ($actor->hasRole(self::ROLE_SUPER_ADMIN_ROOT)) {
            $this->assertCooperativeIdProvided($cooperativeId);

            return;
        }

        if ($actor->hasRole(self::ROLE_SUPER_ADMIN)) {
            $this->assertCooperativeIdProvided($cooperativeId);

            if (
                ! $actor->cooperatives()
                    ->whereKey($cooperativeId)
                    ->exists()
            ) {
                throw new DomainException(
                    'The super admin is not assigned to this cooperative.',
                );
            }

            return;
        }

        if ($actor->hasRole(self::ROLE_COOPERATIVE_ADMIN)) {
            $this->assertCooperativeIdProvided($cooperativeId);

            if (
                $actor->cooperative_id === null
                || (string) $actor->cooperative_id !== (string) $cooperativeId
            ) {
                throw new DomainException(
                    'The cooperative admin can only manage users in their own cooperative.',
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
            $actor->hasRole(self::ROLE_COOPERATIVE_ADMIN) => [
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
        if (! $superAdmin->hasRole(self::ROLE_SUPER_ADMIN)) {
            throw new DomainException(
                'The target user must have the super_admin role.',
            );
        }
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