<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\UserManagement\Services;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Countries\Models\Country;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\UserManagement\Services\UserManagementService;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Localization\Models\Address;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UserManagementServiceTest extends TestCase
{
    use RefreshDatabase;

    private UserManagementService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->service = new UserManagementService();
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
        self::assertTrue($user->hasRole('user'));
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
        self::assertTrue($user->hasRole('user'));
    }

    // public function test_super_admin_cannot_create_user_in_unassigned_cooperative(): void
    // {
    //     $superAdmin = $this->createUserWithRole('super_admin');

    //     $assignedCooperative = $this->createCooperative();

    //     $country = Country::factory()->create();

    //     $unassignedAddress = Address::factory()->create([
    //         'country_id' => $country->id,
    //     ]);

    //     $unassignedCooperative = Cooperative::factory()->create([
    //         'address_id' => $unassignedAddress->id,
    //     ]);

    //     $superAdmin->cooperatives()->attach(
    //         $assignedCooperative->id,
    //     );

    //     $this->expectException(\DomainException::class);

    //     $this->service->createUser(
    //         $superAdmin,
    //         [
    //             'first_name' => 'Blocked',
    //             'last_name' => 'User',
    //             'display_name' => 'Blocked User',
    //             'email' => 'blocked@example.com',
    //             'telephone' => '+212600000003',
    //             'password' => 'Password123',
    //             'status' => UserStatus::Active,
    //             'cooperative_id' => $unassignedCooperative->id,
    //         ],
    //         'user',
    //     );
    // }

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

    public function test_cooperative_admin_can_create_user_in_own_cooperative(): void
    {
        $cooperative = $this->createCooperative();

        $admin = $this->createUserWithRole(
            'cooperative_admin',
            $cooperative->id,
        );

        $user = $this->service->createUser(
            $admin,
            [
                'first_name' => 'Local',
                'last_name' => 'User',
                'display_name' => 'Local User',
                'email' => 'local@example.com',
                'telephone' => '+212600000004',
                'password' => 'Password123',
                'status' => UserStatus::Active,
                'cooperative_id' => $cooperative->id,
            ],
            'user',
        );

        self::assertSame($cooperative->id, $user->cooperative_id);
        self::assertTrue($user->hasRole('user'));
    }

    public function test_cooperative_admin_cannot_create_user_in_another_cooperative(): void
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

    // private function createUserWithRole(
    //     string $roleSlug,
    //     ?string $cooperativeId = null,
    // ): User {
    //     $user = User::factory()->create([
    //         'cooperative_id' => $cooperativeId,
    //     ]);

    //     $role = Role::query()->create([
    //         'name' => $roleSlug,
    //         'slug' => $roleSlug,
    //         'is_active' => true,
    //     ]);

    //     $user->roles()->attach($role);

    //     return $user;
    // }
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
        $country ??= Country::factory()->create();

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
        self::assertTrue($superAdmin->hasRole('super_admin'));
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
}
