<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Identity\Users;

use App\Domains\Localization\Models\Country;
use App\Domains\Identity\Authentication\Http\Middleware\EnsureActiveAuthenticationSession;
use App\Domains\Identity\Authorization\Models\Permission;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PersonalAddressControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureActiveAuthenticationSession::class);

        Country::query()->firstOrCreate(
            ['code' => 'MA'],
            [
                'iso3' => 'MAR',
                'name' => 'Maroc',
                'native_name' => 'المغرب',
                'phone_code' => '+212',
                'currency' => 'MAD',
                'locale' => 'fr',
                'is_active' => true,
            ],
        );
    }

    public function test_internal_user_can_load_and_update_their_personal_address(): void
    {
        $user = $this->userWithRole('user');

        $this->actingAs($user)
            ->get(route('backoffice.profile.address.edit'))
            ->assertOk()
            ->assertSee('Mon adresse personnelle')
            ->assertSee('Tanger-Tétouan-Al Hoceïma')
            ->assertSee('Dakhla-Oued Ed-Dahab');

        $this->actingAs($user)
            ->put(route('backoffice.profile.address.update'), $this->addressAttributes())
            ->assertRedirect(route('backoffice.profile.address.edit'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('addresses', [
            'user_id' => $user->getKey(),
            'number' => '10',
            'street' => 'Avenue Hassan II',
            'city' => 'Casablanca',
            'region' => 'Casablanca-Settat',
            'country_id' => Country::query()->where('code', 'MA')->value('id'),
        ]);
    }

    public function test_root_can_update_a_users_personal_address_from_the_user_fiche(): void
    {
        $root = $this->userWithRole('super_admin_root', ['users.update']);
        $target = $this->userWithRole('user');

        $this->actingAs($root)
            ->put(route('root.backoffice.users.address.update', $target), $this->addressAttributes())
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('addresses', [
            'user_id' => $target->getKey(),
            'street' => 'Avenue Hassan II',
            'region' => 'Casablanca-Settat',
        ]);
    }

    public function test_personal_address_form_rejects_a_region_outside_the_moroccan_list(): void
    {
        $user = $this->userWithRole('user');

        $this->actingAs($user)
            ->put(route('backoffice.profile.address.update'), [
                ...$this->addressAttributes(),
                'region' => 'Région non reconnue',
            ])
            ->assertSessionHasErrors(['region']);

        $this->assertDatabaseMissing('addresses', [
            'user_id' => $user->getKey(),
        ]);
    }

    /** @return array<string, string> */
    private function addressAttributes(): array
    {
        return [
            'number' => '10',
            'street' => 'Avenue Hassan II',
            'floor' => '2e étage',
            'city' => 'Casablanca',
            'region' => 'Casablanca-Settat',
            'postal_code' => '20000',
        ];
    }

    /** @param list<string> $permissions */
    private function userWithRole(string $slug, array $permissions = []): User
    {
        $user = User::factory()->create([
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
        ]);

        $role = Role::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => str($slug)->replace('_', ' ')->title()->toString(), 'is_active' => true],
        );

        foreach ($permissions as $permissionSlug) {
            $permission = Permission::query()->firstOrCreate(
                ['slug' => $permissionSlug],
                ['name' => str($permissionSlug)->replace('.', ' ')->title()->toString(), 'is_active' => true],
            );
            $role->permissions()->syncWithoutDetaching([$permission->getKey()]);
        }

        $user->roles()->syncWithoutDetaching([$role->getKey()]);

        return $user;
    }
}
