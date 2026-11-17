<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Localization\Services;

use App\Domains\Audit\Models\AuditEvent;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Countries\Models\Country;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Localization\Models\Address;
use App\Domains\Localization\Services\AddressService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Tests\TestCase;

final class AddressServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
        ]);
    }

    public function test_service_creates_and_updates_a_users_personal_address_with_audit(): void
    {
        $user = $this->userWithRole('user');
        Country::factory()->create();
        $service = app(AddressService::class);

        $address = $service->createForUser($user, $user, [
            'number' => '12',
            'street' => 'Rue des Fleurs',
            'city' => 'Casablanca',
            'region' => 'Casablanca-Settat',
            'postal_code' => '20000',
        ]);

        self::assertSame($user->getKey(), $address->user_id);
        self::assertNull($address->cooperative_id);
        self::assertSame('MA', $address->country->code);
        self::assertSame($user->getKey(), $address->created_by);

        $updated = $service->updateForUser($user, $user, [
            'number' => '14',
            'street' => 'Rue des Jardins',
            'city' => 'Rabat',
            'region' => 'Rabat-Salé-Kénitra',
            'postal_code' => '10000',
        ]);

        self::assertSame($address->getKey(), $updated->getKey());
        self::assertSame('14', $updated->number);
        self::assertSame('Rabat', $updated->city);
        self::assertSame($user->getKey(), $updated->user_id);
        self::assertNull($updated->cooperative_id);
        self::assertSame($user->getKey(), $updated->updated_by);

        self::assertSame(
            2,
            AuditEvent::query()
                ->where('subject_type', Address::class)
                ->where('subject_id', $address->getKey())
                ->count(),
        );
    }

    public function test_service_rejects_client_controlled_country_and_owner_fields(): void
    {
        $user = $this->userWithRole('user');

        $this->expectException(InvalidArgumentException::class);

        app(AddressService::class)->createForUser($user, $user, [
            'street' => 'Rue de Test',
            'city' => 'Casablanca',
            'country_id' => '01J00000000000000000000000',
        ]);
    }

    public function test_service_rejects_address_creation_when_morocco_is_inactive(): void
    {
        $user = $this->userWithRole('user');

        \App\Domains\Countries\Models\Country::factory()
            ->inactive()
            ->create();

        $this->expectException(ValidationException::class);

        app(AddressService::class)->createForUser($user, $user, [
            'street' => 'Rue de Test',
            'city' => 'Casablanca',
        ]);
    }

    public function test_service_validates_coordinates(): void
    {
        $user = $this->userWithRole('user');

        $this->expectException(ValidationException::class);

        app(AddressService::class)->createForUser($user, $user, [
            'street' => 'Rue de Test',
            'city' => 'Casablanca',
            'latitude' => 95,
        ]);
    }


    public function test_customer_can_keep_five_active_addresses_but_not_a_sixth(): void
    {
        $customer = $this->userWithRole('customer');
        $service = app(AddressService::class);
        for ($i = 1; $i <= 5; $i++) {
            $service->createForUser($customer, $customer, [
                'street' => "Rue {$i}", 'city' => 'Casablanca',
            ]);
        }
        self::assertSame(5, $customer->addresses()->count());
        $this->expectException(ValidationException::class);
        $service->createForUser($customer, $customer, [
            'street' => 'Rue supplémentaire', 'city' => 'Casablanca',
        ]);
    }

    public function test_voluntary_deletion_releases_a_slot_and_promotes_the_oldest_remaining_address(): void
    {
        $customer = $this->userWithRole('customer');
        $service = app(AddressService::class);
        $first = $service->createForUser($customer, $customer, ['street' => 'Rue 1', 'city' => 'Casablanca']);
        $second = $service->createForUser($customer, $customer, ['street' => 'Rue 2', 'city' => 'Rabat']);
        $service->deleteForUser($customer, $customer, $first);
        self::assertSoftDeleted('addresses', ['id' => $first->getKey()]);
        self::assertTrue($second->fresh()->is_primary);
        $replacement = $service->createForUser($customer, $customer, ['street' => 'Rue 3', 'city' => 'Fès']);
        self::assertSame($customer->getKey(), $replacement->user_id);
        self::assertFalse($replacement->is_primary);
    }

    public function test_internal_user_is_limited_to_one_active_address(): void
    {
        $user = $this->userWithRole('user');
        $service = app(AddressService::class);
        $service->createForUser($user, $user, ['street' => 'Rue 1', 'city' => 'Casablanca']);

        $this->expectException(ValidationException::class);
        $service->createForUser($user, $user, ['street' => 'Rue 2', 'city' => 'Rabat']);
    }

    public function test_cooperative_admin_can_create_a_missing_cooperative_address_without_create_permission(): void
    {
        $cooperative = Cooperative::factory()->create();
        $admin = $this->userWithRole('cooperative_admin');
        $admin->forceFill(['cooperative_id' => $cooperative->getKey()])->save();

        $cooperative->address()->firstOrFail()->delete();

        $address = app(AddressService::class)->updateForCooperative($admin, $cooperative, [
            'street' => 'Nouvelle adresse',
            'city' => 'Casablanca',
        ]);

        self::assertSame($cooperative->getKey(), $address->cooperative_id);
        self::assertSame('Nouvelle adresse', $address->street);
        self::assertTrue($address->is_primary);
    }

    public function test_super_admin_cannot_create_an_address_for_an_unassigned_cooperative(): void
    {
        $superAdmin = $this->userWithRole('super_admin');
        $cooperative = Cooperative::factory()->create();

        $this->expectException(AuthorizationException::class);

        app(AddressService::class)->createForCooperative($superAdmin, $cooperative, [
            'street' => 'Rue non autorisée',
            'city' => 'Casablanca',
        ]);
    }

    public function test_cooperative_address_is_restored_only_after_owner_deletion(): void
    {
        $actor = $this->userWithRole('super_admin_root');
        $cooperative = Cooperative::factory()->create();
        $address = $cooperative->address;
        $service = app(AddressService::class);

        $service->softDeleteCooperativeAddresses($cooperative, (string) $actor->getKey());
        $cooperative->delete();
        self::assertSoftDeleted('addresses', ['id' => $address->getKey()]);

        $cooperative->restore();
        $service->restoreLatestCooperativeAddresses($cooperative, (string) $actor->getKey());

        self::assertDatabaseHas('addresses', [
            'id' => $address->getKey(),
            'cooperative_id' => $cooperative->getKey(),
            'deleted_at' => null,
            'deletion_source' => null,
            'is_primary' => true,
        ]);
    }

    private function userWithRole(string $slug): User
    {
        $user = User::factory()->create();
        $role = Role::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => $slug, 'is_active' => true],
        );

        $user->roles()->syncWithoutDetaching([$role->getKey()]);

        return $user;
    }
}
