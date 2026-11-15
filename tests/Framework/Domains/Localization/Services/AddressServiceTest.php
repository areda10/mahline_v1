<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Localization\Services;

use App\Domains\Audit\Models\AuditEvent;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Localization\Models\Address;
use App\Domains\Localization\Services\AddressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Tests\TestCase;

final class AddressServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_creates_and_updates_a_users_personal_address_with_audit(): void
    {
        $user = $this->userWithRole('user');
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
