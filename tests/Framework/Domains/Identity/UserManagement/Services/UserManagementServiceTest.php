<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\UserManagement\Services;

use App\Domains\Audit\Models\AuditEvent;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Countries\Models\Country;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\UserManagement\Services\UserManagementService;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Localization\Models\Address;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

final class UserManagementServiceTest extends TestCase
{
    use RefreshDatabase;

    private UserManagementService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->service = app(UserManagementService::class);
    }

    public function test_super_admin_root_can_create_user_for_any_cooperative(): void
    {
        $root = $this->createUserWithRole('super_admin_root');

        $cooperative = $this->createCooperative();

        $user = $this->service->createUser(
            $root,
            [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'display_name' => 'John Doe',
                'email' => 'john@example.com',
                'telephone' => '+212600000001',
                'password' => 'Password123',
                'status' => UserStatus::Active,
                'cooperative_id' => $cooperative->id,
            ],
            'user',
        );

        self::assertInstanceOf(User::class, $user);
        self::assertSame($cooperative->id, $user->cooperative_id);
        self::assertTrue(
            $user->roles()->where('slug', 'user')->exists(),
        );
    }

    public function test_super_admin_can_create_user_in_assigned_cooperative(): void
    {
        $superAdmin = $this->createUserWithRole('super_admin');

        $cooperative = $this->createCooperative();

        $superAdmin->cooperatives()->attach(
            $cooperative->id,
        );

        $user = $this->service->createUser(
            $superAdmin,
            [
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'display_name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'telephone' => '+212600000002',
                'password' => 'Password123',
                'status' => UserStatus::Active,
                'cooperative_id' => $cooperative->id,
            ],
            'user',
        );

        self::assertSame($cooperative->id, $user->cooperative_id);
        self::assertTrue(
            $user->roles()->where('slug', 'user')->exists(),
        );
    }

    public function test_super_admin_cannot_create_user_in_unassigned_cooperative(): void
    {
        $superAdmin = $this->createUserWithRole('super_admin');
        $country = Country::factory()->create();
        $assignedCooperative = $this->createCooperative($country);
        $unassignedCooperative = $this->createCooperative($country);

        $superAdmin->cooperatives()->attach(
            $assignedCooperative->id,
        );

        $this->expectException(\DomainException::class);

        $this->service->createUser(
            $superAdmin,
            [
                'first_name' => 'Blocked',
                'last_name' => 'User',
                'display_name' => 'Blocked User',
                'email' => 'blocked@example.com',
                'telephone' => '+212600000003',
                'password' => 'Password123',
                'status' => UserStatus::Active,
                'cooperative_id' => $unassignedCooperative->id,
            ],
            'user',
        );
    }

    public function test_cooperative_admin_cannot_create_user_in_own_cooperative(): void
    {
        $cooperative = $this->createCooperative();

        $admin = $this->createUserWithRole(
            'cooperative_admin',
            $cooperative->id,
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(
            'The actor is not authorized to assign the "user" role.',
        );

        $this->service->createUser(
            $admin,
            [
                'first_name' => 'Blocked',
                'last_name' => 'User',
                'display_name' => 'Blocked User',
                'email' => 'blocked-local@example.com',
                'telephone' => '+212600000004',
                'password' => 'Password123',
                'status' => UserStatus::Active,
                'cooperative_id' => $cooperative->id,
            ],
            'user',
        );
    }

    public function test_cooperative_admin_cannot_create_user_outside_own_cooperative(): void
    {
        $country = Country::factory()->create();

        $cooperativeA = $this->createCooperative($country);
        $cooperativeB = $this->createCooperative($country);

        $admin = $this->createUserWithRole(
            'cooperative_admin',
            $cooperativeA->id,
        );

        $this->expectException(\DomainException::class);

        $this->service->createUser(
            $admin,
            [
                'first_name' => 'Blocked',
                'last_name' => 'User',
                'display_name' => 'Blocked User',
                'email' => 'blocked2@example.com',
                'telephone' => '+212600000005',
                'password' => 'Password123',
                'status' => UserStatus::Active,
                'cooperative_id' => $cooperativeB->id,
            ],
            'user',
        );
    }

    public function test_user_cannot_create_another_user(): void
    {
        $cooperative = $this->createCooperative();

        $user = $this->createUserWithRole(
            'user',
            $cooperative->id,
        );

        $this->expectException(\DomainException::class);

        $this->service->createUser(
            $user,
            [
                'first_name' => 'Blocked',
                'last_name' => 'User',
                'display_name' => 'Blocked User',
                'email' => 'blocked3@example.com',
                'telephone' => '+212600000006',
                'password' => 'Password123',
                'status' => UserStatus::Active,
                'cooperative_id' => $cooperative->id,
            ],
            'user',
        );
    }

    public function test_email_role_and_cooperative_scope_changes_revoke_api_tokens(): void
    {
        $root = $this->createUserWithRole('super_admin_root');
        $cooperativeA = $this->createCooperative();
        $cooperativeB = $this->createCooperative();
        $target = $this->createUserWithRole('user', (string) $cooperativeA->getKey());

        $beforeTransfer = $target->createToken('before-transfer');
        $this->service->updateUserAccount(
            $root,
            $target,
            [
                'first_name' => $target->first_name,
                'last_name' => $target->last_name,
                'email' => 'identity-transfer@example.com',
                'telephone' => $target->telephone,
                'cooperative_id' => $cooperativeB->getKey(),
            ],
            'user',
        );
        self::assertNotNull($beforeTransfer->accessToken->fresh()->revoked_at);

        $beforeRoleChange = $target->fresh()->createToken('before-role-change');
        $this->service->updateUserAccount(
            $root,
            $target->fresh(),
            [
                'first_name' => $target->first_name,
                'last_name' => $target->last_name,
                'email' => 'identity-transfer@example.com',
                'telephone' => $target->telephone,
                'cooperative_id' => $cooperativeB->getKey(),
            ],
            'cooperative_admin',
        );
        self::assertNotNull($beforeRoleChange->accessToken->fresh()->revoked_at);
    }

    public function test_cooperative_assignment_changes_revoke_existing_api_tokens(): void
    {
        $root = $this->createUserWithRole('super_admin_root');
        $cooperative = $this->createCooperative();
        $superAdmin = $this->createUserWithRole('super_admin');

        $beforeAssignment = $superAdmin->createToken('before-assignment');
        $this->service->assignSuperAdminToCooperative($root, $superAdmin, $cooperative);

        self::assertNotNull($beforeAssignment->accessToken->fresh()->revoked_at);

        $afterAssignment = $superAdmin->createToken('after-assignment');
        $this->service->removeSuperAdminFromCooperative($root, $superAdmin, $cooperative);

        self::assertNotNull($afterAssignment->accessToken->fresh()->revoked_at);
        $this->assertDatabaseHas('audit_events', [
            'event_key' => 'identity.authentication.api_token_revoked',
            'subject_id' => $afterAssignment->accessToken->getKey(),
        ]);
    }

    public function test_super_admin_root_can_assign_super_admin_to_cooperative(): void
    {
        $root = $this->createUserWithRole('super_admin_root');

        $cooperative = $this->createCooperative();

        $superAdmin = $this->createUserWithRole('super_admin');

        $this->service->assignSuperAdminToCooperative(
            $root,
            $superAdmin,
            $cooperative,
        );

        self::assertTrue(
            $superAdmin->cooperatives()
                ->whereKey($cooperative->id)
                ->exists(),
        );
    }

    public function test_super_admin_root_can_remove_super_admin_assignment(): void
    {
        $root = $this->createUserWithRole('super_admin_root');

        $cooperative = $this->createCooperative();

        $superAdmin = $this->createUserWithRole('super_admin');

        $superAdmin->cooperatives()->attach(
            $cooperative->id,
        );

        $this->service->removeSuperAdminFromCooperative(
            $root,
            $superAdmin,
            $cooperative,
        );

        self::assertFalse(
            $superAdmin->cooperatives()
                ->whereKey($cooperative->id)
                ->exists(),
        );
    }

    public function test_super_admin_cannot_assign_another_super_admin(): void
    {
        $superAdmin = $this->createUserWithRole('super_admin');

        $cooperative = $this->createCooperative();

        $target = $this->createUserWithRole('super_admin');

        $this->expectException(\DomainException::class);

        $this->service->assignSuperAdminToCooperative(
            $superAdmin,
            $target,
            $cooperative,
        );
    }

    public function test_root_temporary_activation_archives_the_existing_password(): void
    {
        $root = $this->createUserWithRole('super_admin_root');
        $target = User::factory()->create([
            'status' => UserStatus::Pending,
            'password' => 'ExistingPassword2026!',
            'email_verified_at' => null,
        ]);

        $activated = $this->service->activateAndVerifyUser($root, $target);

        $this->assertSame(UserStatus::Active, $activated->status);
        $this->assertTrue($activated->must_change_password);
        $this->assertTrue(Hash::check('MahlineMahline', (string) $activated->password));

        $history = DB::table('user_password_histories')
            ->where('user_id', $target->getKey())
            ->first();

        $this->assertNotNull($history);
        $this->assertTrue(Hash::check('ExistingPassword2026!', (string) $history->password_hash));
    }

    public function test_password_history_survives_soft_delete_and_cascades_on_force_delete(): void
    {
        $root = $this->createUserWithRole('super_admin_root');
        $target = User::factory()->create();

        DB::table('user_password_histories')->insert([
            'id' => (string) Str::ulid(),
            'user_id' => $target->getKey(),
            'password_hash' => Hash::make('PreviousPassword2026!'),
            'created_at' => now(),
        ]);

        $this->service->deleteUser($root, $target);

        $this->assertDatabaseHas('user_password_histories', [
            'user_id' => $target->getKey(),
        ]);

        $deletedUser = User::withTrashed()->findOrFail($target->getKey());
        $deletedUser->forceDelete();

        $this->assertDatabaseMissing('user_password_histories', [
            'user_id' => $target->getKey(),
        ]);
    }

    private function createUserWithRole(
        string $roleSlug,
        ?string $cooperativeId = null,
    ): User {
        $user = User::factory()->create([
            'cooperative_id' => $cooperativeId,
        ]);

        $role = Role::query()->firstOrCreate(
            [
                'slug' => $roleSlug,
            ],
            [
                'name' => $roleSlug,
                'is_active' => true,
            ],
        );

        $user->roles()->syncWithoutDetaching([
            $role->id,
        ]);

        return $user;
    }

    private function createCooperative(
        ?Country $country = null,
    ): Cooperative {
        $country ??= Country::query()->firstOrCreate(
            ['code' => 'MA'],
            [
                'iso3' => 'MAR',
                'name' => 'Morocco',
                'native_name' => 'المغرب',
                'phone_code' => '+212',
                'currency' => 'MAD',
                'locale' => 'fr',
                'is_active' => true,
            ],
        );

        $address = Address::factory()->create([
            'country_id' => $country->id,
        ]);

        return Cooperative::factory()->create([
            'address_id' => $address->id,
        ]);
    }

    public function test_unknown_role_cannot_be_assigned_during_user_creation(): void
    {
        $root = $this->createUserWithRole('super_admin_root');
        $cooperative = $this->createCooperative();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(
            'Unknown role "unknown_role".',
        );

        $this->service->createUser(
            $root,
            [
                'first_name' => 'Unknown',
                'last_name' => 'Role',
                'display_name' => 'Unknown Role',
                'email' => 'unknown-role@example.com',
                'telephone' => '+212600000007',
                'password' => 'Password123',
                'status' => UserStatus::Active,
                'cooperative_id' => $cooperative->id,
            ],
            'unknown_role',
        );
    }

    public function test_cooperative_admin_cannot_create_super_admin(): void
    {
        $country = Country::factory()->create();
        $cooperative = $this->createCooperative($country);

        $actor = $this->createUserWithRole(
            'cooperative_admin',
            $cooperative->id,
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(
            'The actor is not authorized to assign the "super_admin" role.',
        );

        $this->service->createUser(
            $actor,
            [
                'first_name' => 'Forbidden',
                'last_name' => 'Admin',
                'display_name' => 'Forbidden Admin',
                'email' => 'forbidden-super-admin@example.com',
                'telephone' => '+212600000008',
                'password' => 'Password123',
                'status' => UserStatus::Active,
                'cooperative_id' => $cooperative->id,
            ],
            'super_admin',
        );
    }

    public function test_super_admin_cannot_create_another_super_admin(): void
    {
        $country = Country::factory()->create();
        $cooperative = $this->createCooperative($country);

        $actor = $this->createUserWithRole('super_admin');

        $actor->cooperatives()->syncWithoutDetaching([
            $cooperative->id,
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(
            'The actor is not authorized to assign the "super_admin" role.',
        );

        $this->service->createUser(
            $actor,
            [
                'first_name' => 'Forbidden',
                'last_name' => 'Admin',
                'display_name' => 'Forbidden Admin',
                'email' => 'forbidden-super-admin-2@example.com',
                'telephone' => '+212600000009',
                'password' => 'Password123',
                'status' => UserStatus::Active,
                'cooperative_id' => $cooperative->id,
            ],
            'super_admin',
        );
    }

    public function test_super_admin_root_cannot_create_another_super_admin_root(): void
    {
        $country = Country::factory()->create();
        $cooperative = $this->createCooperative($country);

        $actor = $this->createUserWithRole('super_admin_root');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(
            'The actor is not authorized to assign the "super_admin_root" role.',
        );

        $this->service->createUser(
            $actor,
            [
                'first_name' => 'Forbidden',
                'last_name' => 'Root',
                'display_name' => 'Forbidden Root',
                'email' => 'forbidden-root@example.com',
                'telephone' => '+212600000010',
                'password' => 'Password123',
                'status' => UserStatus::Active,
                'cooperative_id' => $cooperative->id,
            ],
            'super_admin_root',
        );
    }

    public function test_super_admin_created_by_root_has_no_direct_cooperative(): void
    {
        $country = Country::factory()->create();
        $cooperative = $this->createCooperative($country);

        $root = $this->createUserWithRole('super_admin_root');

        $superAdmin = $this->service->createUser(
            $root,
            [
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'display_name' => 'Super Admin',
                'email' => 'super-admin-without-cooperative@example.com',
                'telephone' => '+212600000011',
                'password' => 'Password123',
                'status' => UserStatus::Active,
                'cooperative_id' => $cooperative->id,
            ],
            'super_admin',
        );

        self::assertNull($superAdmin->cooperative_id);
        self::assertCount(0, $superAdmin->cooperatives);
    }

    public function test_super_admin_root_can_create_super_admin_without_cooperative(): void
    {
        $root = $this->createUserWithRole('super_admin_root');

        $superAdmin = $this->service->createUser(
            $root,
            [
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'display_name' => 'Super Admin',
                'email' => 'super-admin-no-cooperative@example.com',
                'telephone' => '+212600000012',
                'password' => 'Password123',
                'status' => UserStatus::Active,
            ],
            'super_admin',
        );

        self::assertNull($superAdmin->cooperative_id);
        self::assertTrue(
            $superAdmin->roles()->where('slug', 'super_admin')->exists(),
        );
        self::assertCount(0, $superAdmin->cooperatives);
    }

    public function test_inactive_role_cannot_be_assigned_during_user_creation(): void
    {
        $root = $this->createUserWithRole('super_admin_root');

        $cooperative = $this->createCooperative();

        Role::query()
            ->where('slug', 'user')
            ->update(['is_active' => false]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(
            'Role "user" is inactive.',
        );

        $this->service->createUser(
            $root,
            [
                'first_name' => 'Inactive',
                'last_name' => 'Role',
                'display_name' => 'Inactive Role',
                'email' => 'inactive-role@example.com',
                'telephone' => '+212600000013',
                'password' => 'Password123',
                'status' => UserStatus::Active,
                'cooperative_id' => $cooperative->id,
            ],
            'user',
        );
    }

    public function test_super_admin_root_can_remove_assignment_from_inactive_super_admin(): void
    {
        $root = $this->createUserWithRole('super_admin_root');

        $cooperative = $this->createCooperative();

        $superAdmin = $this->createUserWithRole('super_admin');

        $superAdmin->cooperatives()->attach(
            $cooperative->id,
        );

        $superAdmin->update([
            'status' => UserStatus::Inactive,
        ]);

        $this->service->removeSuperAdminFromCooperative(
            $root,
            $superAdmin,
            $cooperative,
        );

        self::assertFalse(
            $superAdmin->cooperatives()
                ->whereKey($cooperative->id)
                ->exists(),
        );
    }

    public function test_creating_user_records_audit_event(): void
    {
        $root = $this->createUserWithRole('super_admin_root');
        $cooperative = $this->createCooperative();

        $user = $this->service->createUser(
            $root,
            [
                'first_name' => 'Audit',
                'last_name' => 'Test',
                'display_name' => 'Audit Test',
                'email' => 'audit-test@example.com',
                'telephone' => '+212600000020',
                'password' => 'Password123',
                'status' => UserStatus::Active,
                'cooperative_id' => $cooperative->id,
            ],
            'user',
        );

        $this->assertDatabaseHas('audit_events', [
            'event_key' => 'identity.user.created',
            'action' => 'created',
            'actor_id' => $root->id,
            'cooperative_id' => $cooperative->id,
            'subject_id' => $user->id,
        ]);

        $event = AuditEvent::query()
            ->where('event_key', 'identity.user.created')
            ->where('subject_id', $user->id)
            ->firstOrFail();

        self::assertSame('user', $event->new_values['role']);
        self::assertArrayNotHasKey('password', $event->new_values);
    }

    public function test_assigning_super_admin_records_audit_event(): void
    {
        $root = $this->createUserWithRole('super_admin_root');
        $cooperative = $this->createCooperative();
        $superAdmin = $this->createUserWithRole('super_admin');

        $this->service->assignSuperAdminToCooperative(
            $root,
            $superAdmin,
            $cooperative,
        );

        $this->assertDatabaseHas('audit_events', [
            'event_key' => 'identity.super_admin.cooperative_assigned',
            'action' => 'assigned',
            'actor_id' => $root->id,
            'cooperative_id' => $cooperative->id,
            'subject_id' => $superAdmin->id,
        ]);
    }

    public function test_removing_super_admin_records_audit_event(): void
    {
        $root = $this->createUserWithRole('super_admin_root');
        $cooperative = $this->createCooperative();
        $superAdmin = $this->createUserWithRole('super_admin');

        $superAdmin->cooperatives()->attach($cooperative->id);

        $this->service->removeSuperAdminFromCooperative(
            $root,
            $superAdmin,
            $cooperative,
        );

        $this->assertDatabaseHas('audit_events', [
            'event_key' => 'identity.super_admin.cooperative_unassigned',
            'action' => 'unassigned',
            'actor_id' => $root->id,
            'cooperative_id' => $cooperative->id,
            'subject_id' => $superAdmin->id,
        ]);
    }

    public function test_repeated_super_admin_assignment_is_idempotent(): void
    {
        $root = $this->createUserWithRole('super_admin_root');
        $cooperative = $this->createCooperative();
        $superAdmin = $this->createUserWithRole('super_admin');

        $this->service->assignSuperAdminToCooperative(
            $root,
            $superAdmin,
            $cooperative,
        );

        $this->service->assignSuperAdminToCooperative(
            $root,
            $superAdmin,
            $cooperative,
        );

        $this->assertDatabaseCount('super_admin_cooperative', 1);

        $this->assertDatabaseCount('audit_events', 1);

        $this->assertDatabaseHas('audit_events', [
            'event_key' => 'identity.super_admin.cooperative_assigned',
            'action' => 'assigned',
            'actor_id' => $root->id,
            'cooperative_id' => $cooperative->id,
            'subject_id' => $superAdmin->id,
        ]);
    }

    public function test_removing_non_existing_assignment_does_not_record_audit_event(): void
    {
        $root = $this->createUserWithRole('super_admin_root');
        $cooperative = $this->createCooperative();
        $superAdmin = $this->createUserWithRole('super_admin');

        $this->service->removeSuperAdminFromCooperative(
            $root,
            $superAdmin,
            $cooperative,
        );

        $this->assertDatabaseMissing('audit_events', [
            'event_key' => 'identity.super_admin.cooperative_unassigned',
            'actor_id' => $root->id,
            'cooperative_id' => $cooperative->id,
            'subject_id' => $superAdmin->id,
        ]);
    }

    public function test_audit_event_is_rolled_back_with_business_transaction(): void
    {
        $root = $this->createUserWithRole('super_admin_root');
        $cooperative = $this->createCooperative();

        try {
            DB::transaction(
                function () use ($root, $cooperative): void {
                    $this->service->createUser(
                        $root,
                        [
                            'first_name' => 'Rollback',
                            'last_name' => 'Test',
                            'display_name' => 'Rollback Test',
                            'email' => 'rollback@example.com',
                            'telephone' => '+212600000021',
                            'password' => 'Password123',
                            'status' => UserStatus::Active,
                            'cooperative_id' => $cooperative->id,
                        ],
                        'user',
                    );

                    throw new \RuntimeException('Force rollback.');
                },
            );
        } catch (\RuntimeException $exception) {
            self::assertSame('Force rollback.', $exception->getMessage());
        }

        $this->assertDatabaseMissing('audit_events', [
            'event_key' => 'identity.user.created',
            'cooperative_id' => $cooperative->id,
        ]);

        $this->assertDatabaseMissing('users', [
            'email' => 'rollback@example.com',
        ]);
    }

    public function test_database_rejects_duplicate_super_admin_cooperative_assignment(): void
    {
        $root = $this->createUserWithRole('super_admin_root');
        $cooperative = $this->createCooperative();
        $superAdmin = $this->createUserWithRole('super_admin');

        $this->service->assignSuperAdminToCooperative(
            $root,
            $superAdmin,
            $cooperative,
        );

        $this->expectException(QueryException::class);

        DB::table('super_admin_cooperative')->insert([
            'id' => (string) Str::ulid(),
            'super_admin_id' => $superAdmin->id,
            'cooperative_id' => $cooperative->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
