<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Authorization\Policies;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authorization\Models\Permission;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

final class UserPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_can_view_users_across_cooperatives(): void
    {
        $root = $this->userWithRoleAndPermissions('super_admin_root', ['users.view']);
        $target = User::factory()->create();

        self::assertTrue(Gate::forUser($root)->allows('view', $target));
    }

    public function test_super_admin_can_view_only_users_in_assigned_cooperatives(): void
    {
        $assignedCooperative = $this->createCooperative();
        $otherCooperative = $this->createCooperative();

        $admin = $this->userWithRoleAndPermissions('super_admin', ['users.view']);
        $admin->cooperatives()->attach($assignedCooperative->getKey());

        $assignedUser = User::factory()->create([
            'cooperative_id' => $assignedCooperative->getKey(),
        ]);
        $unassignedUser = User::factory()->create([
            'cooperative_id' => $otherCooperative->getKey(),
        ]);

        self::assertTrue(Gate::forUser($admin)->allows('view', $assignedUser));
        self::assertFalse(Gate::forUser($admin)->allows('view', $unassignedUser));
    }

    public function test_root_can_view_assignments_of_any_super_admin(): void
    {
        $root = $this->userWithRoleAndPermissions('super_admin_root', ['users.view']);
        $target = $this->userWithRoleAndPermissions('super_admin', []);

        self::assertTrue(Gate::forUser($root)->allows('viewAssignments', $target));
    }

    public function test_super_admin_can_view_only_its_own_assignments(): void
    {
        $actor = $this->userWithRoleAndPermissions('super_admin', ['users.view']);
        $other = $this->userWithRoleAndPermissions('super_admin', []);

        self::assertTrue(Gate::forUser($actor)->allows('viewAssignments', $actor));
        self::assertFalse(Gate::forUser($actor)->allows('viewAssignments', $other));
    }

    public function test_cooperative_admin_cannot_view_super_admin_assignments(): void
    {
        $actor = $this->userWithRoleAndPermissions('cooperative_admin', ['users.view']);
        $target = $this->userWithRoleAndPermissions('super_admin', []);

        self::assertFalse(Gate::forUser($actor)->allows('viewAssignments', $target));
    }

    public function test_only_root_can_assign_an_active_super_admin_to_a_cooperative(): void
    {
        $cooperative = $this->createCooperative();
        $root = $this->userWithRoleAndPermissions(
            'super_admin_root',
            ['users.assign_super_admin'],
        );
        $superAdmin = $this->userWithRoleAndPermissions('super_admin', []);
        $otherAdmin = $this->userWithRoleAndPermissions(
            'super_admin',
            ['users.assign_super_admin'],
        );

        self::assertTrue(Gate::forUser($root)->allows(
            'assignCooperative',
            [$superAdmin, (string) $cooperative->getKey()],
        ));
        self::assertFalse(Gate::forUser($otherAdmin)->allows(
            'assignCooperative',
            [$superAdmin, (string) $cooperative->getKey()],
        ));

        $superAdmin->status = UserStatus::Suspended;
        $superAdmin->save();

        self::assertFalse(Gate::forUser($root)->allows(
            'assignCooperative',
            [$superAdmin, (string) $cooperative->getKey()],
        ));
    }

    public function test_only_root_can_unassign_a_super_admin_even_when_target_is_inactive(): void
    {
        $cooperative = $this->createCooperative();
        $root = $this->userWithRoleAndPermissions(
            'super_admin_root',
            ['users.unassign_super_admin'],
        );
        $superAdmin = $this->userWithRoleAndPermissions('super_admin', [
            'users.unassign_super_admin',
        ]);
        $otherAdmin = $this->userWithRoleAndPermissions('super_admin', []);

        $superAdmin->status = UserStatus::Suspended;
        $superAdmin->save();

        self::assertTrue(Gate::forUser($root)->allows(
            'unassignCooperative',
            [$superAdmin, (string) $cooperative->getKey()],
        ));
        self::assertFalse(Gate::forUser($otherAdmin)->allows(
            'unassignCooperative',
            [$superAdmin, (string) $cooperative->getKey()],
        ));
    }

    public function test_cooperative_admin_can_view_only_users_in_own_cooperative(): void
    {
        $cooperative = $this->createCooperative();
        $otherCooperative = $this->createCooperative();

        $admin = $this->userWithRoleAndPermissions(
            'cooperative_admin',
            ['users.view'],
            ['cooperative_id' => $cooperative->getKey()],
        );
        $ownUser = User::factory()->create([
            'cooperative_id' => $cooperative->getKey(),
        ]);
        $otherUser = User::factory()->create([
            'cooperative_id' => $otherCooperative->getKey(),
        ]);

        self::assertTrue(Gate::forUser($admin)->allows('view', $ownUser));
        self::assertFalse(Gate::forUser($admin)->allows('view', $otherUser));
    }

    public function test_only_root_can_create_managed_accounts(): void
    {
        $root = $this->userWithRoleAndPermissions('super_admin_root', ['users.create']);
        $superAdmin = $this->userWithRoleAndPermissions('super_admin', ['users.create']);

        self::assertTrue(Gate::forUser($root)->allows('create', User::class));
        self::assertFalse(Gate::forUser($superAdmin)->allows('create', User::class));
    }

    public function test_cooperative_admin_cannot_create_users(): void
    {
        $admin = $this->userWithRoleAndPermissions(
            'cooperative_admin',
            ['users.create'],
        );

        self::assertFalse(Gate::forUser($admin)->allows('create', User::class));
    }

    public function test_root_can_resend_invitation_to_pending_user(): void
    {
        $root = $this->userWithRoleAndPermissions(
            'super_admin_root',
            ['users.invite'],
        );

        $target = User::factory()->create([
            'status' => UserStatus::Pending,
            'email_verified_at' => null,
        ]);

        self::assertTrue(
            Gate::forUser($root)->allows('resendInvitation', $target),
        );
    }

    public function test_super_admin_can_resend_invitation_to_user_in_assigned_cooperative(): void
    {
        $cooperative = $this->createCooperative();

        $admin = $this->userWithRoleAndPermissions(
            'super_admin',
            ['users.invite'],
        );
        $admin->cooperatives()->attach($cooperative->getKey());

        $target = $this->userWithRoleAndPermissions('user', [], [
            'cooperative_id' => $cooperative->getKey(),
            'status' => UserStatus::Pending,
            'email_verified_at' => null,
        ]);

        self::assertTrue(
            Gate::forUser($admin)->allows('resendInvitation', $target),
        );
    }

    public function test_super_admin_cannot_resend_invitation_outside_assigned_cooperatives(): void
    {
        $cooperative = $this->createCooperative();

        $admin = $this->userWithRoleAndPermissions(
            'super_admin',
            ['users.invite'],
        );

        $target = $this->userWithRoleAndPermissions('user', [], [
            'cooperative_id' => $cooperative->getKey(),
            'status' => UserStatus::Pending,
            'email_verified_at' => null,
        ]);

        self::assertFalse(
            Gate::forUser($admin)->allows('resendInvitation', $target),
        );
    }

    public function test_cooperative_admin_can_resend_invitation_to_user_in_own_cooperative(): void
    {
        $cooperative = $this->createCooperative();

        $admin = $this->userWithRoleAndPermissions(
            'cooperative_admin',
            ['users.invite'],
            ['cooperative_id' => $cooperative->getKey()],
        );

        $target = $this->userWithRoleAndPermissions('user', [], [
            'cooperative_id' => $cooperative->getKey(),
            'status' => UserStatus::Pending,
            'email_verified_at' => null,
        ]);

        self::assertTrue(
            Gate::forUser($admin)->allows('resendInvitation', $target),
        );
    }

    public function test_cooperative_admin_cannot_resend_invitation_outside_own_cooperative(): void
    {
        $own = $this->createCooperative();
        $other = $this->createCooperative();
        $admin = $this->userWithRoleAndPermissions('cooperative_admin', ['users.invite'], [
            'cooperative_id' => $own->getKey(),
        ]);
        $target = $this->userWithRoleAndPermissions('user', [], [
            'cooperative_id' => $other->getKey(),
            'status' => UserStatus::Pending,
            'email_verified_at' => null,
        ]);

        self::assertFalse(Gate::forUser($admin)->allows('resendInvitation', $target));
    }

    public function test_root_can_update_inactive_account_using_its_active_database_role(): void
    {
        $root = $this->userWithRoleAndPermissions('super_admin_root', ['users.update']);
        $target = $this->userWithRoleAndPermissions('user', [], [
            'status' => UserStatus::Inactive,
        ]);

        self::assertTrue(Gate::forUser($root)->allows('update', $target));
    }

    public function test_cooperative_admin_still_recognizes_inactive_peer_admin_role(): void
    {
        $cooperative = $this->createCooperative();
        $actor = $this->userWithRoleAndPermissions('cooperative_admin', ['users.view'], [
            'cooperative_id' => $cooperative->getKey(),
        ]);
        $peer = $this->userWithRoleAndPermissions('cooperative_admin', [], [
            'cooperative_id' => $cooperative->getKey(),
            'status' => UserStatus::Inactive,
        ]);

        self::assertFalse(Gate::forUser($actor)->allows('view', $peer));
    }

    public function test_assignment_policy_requires_authorized_email_verified_super_admin(): void
    {
        $cooperative = $this->createCooperative();
        $root = $this->userWithRoleAndPermissions('super_admin_root', ['users.assign_super_admin']);
        $unverified = $this->userWithRoleAndPermissions('super_admin', [], [
            'email_verified_at' => null,
        ]);

        self::assertFalse(Gate::forUser($root)->allows(
            'assignCooperative',
            [$unverified, (string) $cooperative->getKey()],
        ));
    }

    public function test_scoped_administrators_can_send_password_reset_only_to_users_in_scope(): void
    {
        $own = $this->createCooperative();
        $other = $this->createCooperative();
        $superAdmin = $this->userWithRoleAndPermissions('super_admin', ['users.invite']);
        $superAdmin->cooperatives()->attach($own->getKey());
        $cooperativeAdmin = $this->userWithRoleAndPermissions('cooperative_admin', ['users.invite'], [
            'cooperative_id' => $own->getKey(),
        ]);
        $ownUser = $this->userWithRoleAndPermissions('user', [], [
            'cooperative_id' => $own->getKey(),
        ]);
        $otherUser = $this->userWithRoleAndPermissions('user', [], [
            'cooperative_id' => $other->getKey(),
        ]);

        self::assertTrue(Gate::forUser($superAdmin)->allows('sendPasswordReset', $ownUser));
        self::assertTrue(Gate::forUser($cooperativeAdmin)->allows('sendPasswordReset', $ownUser));
        self::assertFalse(Gate::forUser($superAdmin)->allows('sendPasswordReset', $otherUser));
        self::assertFalse(Gate::forUser($cooperativeAdmin)->allows('sendPasswordReset', $otherUser));
    }

    public function test_user_cannot_resend_invitation(): void
    {
        $actor = $this->userWithRoleAndPermissions(
            'user',
            ['users.invite'],
        );

        $target = User::factory()->create([
            'status' => UserStatus::Pending,
        ]);

        self::assertFalse(
            Gate::forUser($actor)->allows('resendInvitation', $target),
        );
    }

    public function test_invitation_cannot_be_resent_to_user_who_is_not_pending(): void
    {
        $root = $this->userWithRoleAndPermissions(
            'super_admin_root',
            ['users.invite'],
        );

        $target = User::factory()->create([
            'status' => UserStatus::Active,
        ]);

        self::assertFalse(
            Gate::forUser($root)->allows('resendInvitation', $target),
        );
    }

    public function test_root_cannot_change_own_status(): void
    {
        $root = $this->userWithRoleAndPermissions('super_admin_root', ['users.deactivate']);

        self::assertFalse(Gate::forUser($root)->allows('deactivate', $root));
    }

    public function test_super_admin_cannot_change_own_status(): void
    {
        $admin = $this->userWithRoleAndPermissions('super_admin', ['users.deactivate']);

        self::assertFalse(Gate::forUser($admin)->allows('deactivate', $admin));
    }

    public function test_super_admin_cannot_reactivate_a_suspended_user(): void
    {
        $cooperative = $this->createCooperative();
        $admin = $this->userWithRoleAndPermissions('super_admin', ['users.activate']);
        $admin->cooperatives()->attach($cooperative->getKey());

        $suspended = User::factory()->create([
            'cooperative_id' => $cooperative->getKey(),
            'status' => UserStatus::Suspended,
        ]);

        self::assertFalse(Gate::forUser($admin)->allows('activate', $suspended));
    }

    public function test_cooperative_admin_cannot_change_user_status(): void
    {
        $cooperative = $this->createCooperative();
        $admin = $this->userWithRoleAndPermissions(
            'cooperative_admin',
            ['users.activate', 'users.deactivate', 'users.suspend'],
            ['cooperative_id' => $cooperative->getKey()],
        );
        $target = User::factory()->create([
            'cooperative_id' => $cooperative->getKey(),
            'status' => UserStatus::Pending,
        ]);

        self::assertFalse(Gate::forUser($admin)->allows('activate', $target));
        self::assertFalse(Gate::forUser($admin)->allows('deactivate', $target));
        self::assertFalse(Gate::forUser($admin)->allows('suspend', $target));
    }

    public function test_only_root_can_archive_and_soft_delete_users(): void
    {
        $root = $this->userWithRoleAndPermissions(
            'super_admin_root',
            ['users.archive', 'users.delete'],
        );
        $admin = $this->userWithRoleAndPermissions(
            'super_admin',
            ['users.archive', 'users.delete'],
        );
        $target = User::factory()->create();
        $protectedRoot = $this->userWithRoleAndPermissions('super_admin_root', []);

        self::assertTrue(Gate::forUser($root)->allows('archive', $target));
        self::assertTrue(Gate::forUser($root)->allows('delete', $target));
        self::assertFalse(Gate::forUser($root)->allows('delete', $protectedRoot));
        self::assertFalse(Gate::forUser($admin)->allows('archive', $target));
        self::assertFalse(Gate::forUser($admin)->allows('delete', $target));
    }

    public function test_root_account_is_excluded_from_all_managed_lifecycle_mutations(): void
    {
        $permissions = [
            'users.update',
            'users.invite',
            'users.activate',
            'users.deactivate',
            'users.suspend',
            'users.archive',
            'users.restore',
            'users.delete',
            'users.restore_deleted',
        ];
        $actor = $this->userWithRoleAndPermissions('super_admin_root', $permissions);
        $protectedRoot = $this->userWithRoleAndPermissions('super_admin_root', []);

        self::assertFalse(Gate::forUser($actor)->allows('update', $protectedRoot));
        self::assertFalse(Gate::forUser($actor)->allows('resendInvitation', $protectedRoot));
        self::assertFalse(Gate::forUser($actor)->allows('deactivate', $protectedRoot));
        self::assertFalse(Gate::forUser($actor)->allows('suspend', $protectedRoot));
        self::assertFalse(Gate::forUser($actor)->allows('archive', $protectedRoot));
        self::assertFalse(Gate::forUser($actor)->allows('delete', $protectedRoot));

        try {
            $protectedRoot->status = UserStatus::Inactive;
            $protectedRoot->save();
            self::fail('A Root status must not be changed through Eloquent.');
        } catch (\DomainException $exception) {
            self::assertSame(
                'The super admin root account status cannot be changed.',
                $exception->getMessage(),
            );
        }

        self::assertSame(UserStatus::Active, $protectedRoot->fresh()->status);
        self::assertFalse(Gate::forUser($actor)->allows('activate', $protectedRoot));

        // An active Root is never an archived or soft-deleted restoration target.
        self::assertFalse(Gate::forUser($actor)->allows('restore', $protectedRoot));
        self::assertFalse(Gate::forUser($actor)->allows('restoreDeleted', $protectedRoot));
    }

    public function test_super_admin_can_update_only_users_in_assigned_cooperatives(): void
    {
        $cooperative = $this->createCooperative();
        $actor = $this->userWithRoleAndPermissions('super_admin', ['users.update']);
        $actor->cooperatives()->attach($cooperative->getKey());

        $user = $this->userWithRoleAndPermissions('user', [], [
            'cooperative_id' => $cooperative->getKey(),
        ]);
        $cooperativeAdmin = $this->userWithRoleAndPermissions('cooperative_admin', [], [
            'cooperative_id' => $cooperative->getKey(),
        ]);

        self::assertTrue(Gate::forUser($actor)->allows('update', $user));
        self::assertFalse(Gate::forUser($actor)->allows('update', $cooperativeAdmin));
    }

    public function test_cooperative_admin_can_update_only_users_in_own_cooperative(): void
    {
        $own = $this->createCooperative();
        $other = $this->createCooperative();
        $actor = $this->userWithRoleAndPermissions('cooperative_admin', ['users.update'], [
            'cooperative_id' => $own->getKey(),
        ]);

        $ownUser = $this->userWithRoleAndPermissions('user', [], [
            'cooperative_id' => $own->getKey(),
        ]);
        $otherUser = $this->userWithRoleAndPermissions('user', [], [
            'cooperative_id' => $other->getKey(),
        ]);
        $peerAdmin = $this->userWithRoleAndPermissions('cooperative_admin', [], [
            'cooperative_id' => $own->getKey(),
        ]);

        self::assertTrue(Gate::forUser($actor)->allows('update', $ownUser));
        self::assertFalse(Gate::forUser($actor)->allows('update', $otherUser));
        self::assertFalse(Gate::forUser($actor)->allows('update', $peerAdmin));
        self::assertFalse(Gate::forUser($actor)->allows('view', $peerAdmin));
    }

    public function test_root_classifies_suspended_target_by_active_role_for_lifecycle_permissions(): void
    {
        $root = $this->userWithRoleAndPermissions('super_admin_root', [
            'users.update',
            'users.archive',
        ]);
        $target = $this->userWithRoleAndPermissions('user', [], [
            'status' => UserStatus::Suspended,
            'email_verified_at' => null,
        ]);

        self::assertTrue(Gate::forUser($root)->allows('update', $target));
        self::assertTrue(Gate::forUser($root)->allows('archive', $target));
    }

    public function test_managed_target_without_an_active_operational_role_is_not_classified_as_managed(): void
    {
        $root = $this->userWithRoleAndPermissions('super_admin_root', ['users.update']);
        $target = $this->userWithRoleAndPermissions('user', [], [
            'status' => UserStatus::Suspended,
        ]);
        Role::query()->where('slug', 'user')->update(['is_active' => false]);

        self::assertFalse(Gate::forUser($root)->allows('update', $target));
    }

    private function createCooperative(): Cooperative
    {
        return Cooperative::factory()->create();
    }

    /**
     * @param  list<string>  $permissionSlugs
     * @param  array<string, mixed>  $attributes
     */
    private function userWithRoleAndPermissions(
        string $roleSlug,
        array $permissionSlugs,
        array $attributes = [],
    ): User {
        $user = User::factory()->create($attributes);

        $role = Role::query()->firstOrCreate(
            ['slug' => $roleSlug],
            ['name' => $roleSlug, 'is_active' => true],
        );

        foreach ($permissionSlugs as $slug) {
            $permission = Permission::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $slug, 'description' => $slug, 'is_active' => true],
            );
            $role->permissions()->syncWithoutDetaching([$permission->getKey()]);
        }

        $user->roles()->syncWithoutDetaching([$role->getKey()]);

        return $user;
    }
}
