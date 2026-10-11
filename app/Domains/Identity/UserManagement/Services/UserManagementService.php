<?php

declare(strict_types=1);

namespace App\Domains\Identity\UserManagement\Services;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Users\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Model;

final class UserManagementService
{
    private const ROLE_SUPER_ADMIN_ROOT = 'super_admin_root';

    private const ROLE_SUPER_ADMIN = 'super_admin';

    private const ROLE_COOPERATIVE_ADMIN = 'cooperative_admin';

    private const ROLE_USER = 'user';

    /**
     * Create a user within the actor's authorized scope.
     *
     * @param array<string, mixed> $attributes
     */
    public function createUser(
        User $actor,
        array $attributes,
        string $roleSlug,
    ): User {
        $cooperativeId = $attributes['cooperative_id'] ?? null;

        $this->assertCanCreateUser(
            $actor,
            $cooperativeId,
        );

        $role = $this->findRole($roleSlug);
        
        $user = User::create($attributes);

        $user->roles()->attach($role->id);

        return $user;
    }

    /**
     * Assign a cooperative to a super admin.
     */
    public function assignSuperAdminToCooperative(
        User $actor,
        User $superAdmin,
        Cooperative $cooperative,
    ): void {
        $this->assertSuperAdminRoot($actor);

        if (! $superAdmin->hasRole(self::ROLE_SUPER_ADMIN)) {
             throw new DomainException(
                'The target user must have the super_admin role.',
            );
        }

        $superAdmin->cooperatives()->syncWithoutDetaching([
            $cooperative->id,
        ]);
    }

    /**
     * Remove a cooperative assignment from a super admin.
     */
    public function removeSuperAdminFromCooperative(
        User $actor,
        User $superAdmin,
        Cooperative $cooperative,
    ): void {
        $this->assertSuperAdminRoot($actor);

        if (! $superAdmin->hasRole(self::ROLE_SUPER_ADMIN)) {
             throw new DomainException(
                'The target user must have the super_admin role.',
            );
        }

        $superAdmin->cooperatives()->detach($cooperative->id);
    }

    /**
     * @param string|int|null $cooperativeId
     */
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

            if (! $actor->cooperatives()
                ->whereKey($cooperativeId)
                ->exists()) {
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
                || $actor->cooperative_id !== $cooperativeId
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

    /**
     * @param string|int|null $cooperativeId
     */
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

    private function findRole(string $roleSlug): Role 
    { 
        $role = Role::query() 
        ->where('slug', $roleSlug) 
        ->first(); 
        
        if ($role === null) { 
            throw new \DomainException( 
                sprintf('Unknown role "%s".', $roleSlug), 
            ); 
        } 
        return $role; 
    }
}