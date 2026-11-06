<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Cooperatives\Http\Controllers;

use App\Domains\Cooperatives\Enums\CooperativeStatus;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Countries\Models\Country;
use App\Domains\Identity\Authorization\Models\Permission;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Authentication\Http\Middleware\EnsureActiveAuthenticationSession;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Localization\Models\Address;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

final class CooperativeControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Les tests du contrôleur coopérative isolent les règles métier et d'autorisation.
        // Le middleware de session MAHLINE est couvert par ses tests d'intégration dédiés.
        $this->withoutMiddleware(EnsureActiveAuthenticationSession::class);
    }

    public function test_super_admin_index_lists_only_assigned_cooperatives(): void
    {
        $assigned = Cooperative::factory()->create();
        $unassigned = Cooperative::factory()->create();
        $actor = $this->actor('super_admin', [
            'cooperatives.view',
            'cooperatives.create',
            'cooperatives.update',
        ]);
        $actor->cooperatives()->attach($assigned->getKey());

        $response = $this->actingAs($actor)->get(route('backoffice.cooperatives.index'));

        $response->assertOk()
            ->assertViewIs('backoffice.cooperatives.index')
            ->assertSee($assigned->name)
            ->assertDontSee($unassigned->name);
    }

    public function test_super_admin_can_create_and_is_automatically_assigned(): void
    {
        $actor = $this->actor('super_admin', ['cooperatives.view', 'cooperatives.create']);

        $response = $this->actingAs($actor)->post(route('backoffice.cooperatives.store'), [
            ...$this->validCooperativeAttributes('Coopérative HTTP'),
        ]);

        $response->assertSessionHasNoErrors();
        $cooperative = Cooperative::query()->where('name', 'Coopérative HTTP')->firstOrFail();

        $response->assertRedirect(route('backoffice.cooperatives.show', $cooperative));
        $this->assertDatabaseHas('super_admin_cooperative', [
            'super_admin_id' => $actor->getKey(),
            'cooperative_id' => $cooperative->getKey(),
        ]);
    }

    public function test_root_can_create_without_pivot_assignment(): void
    {
        $actor = $this->actor('super_admin_root', ['cooperatives.view', 'cooperatives.create']);

        $response = $this->actingAs($actor)->post(route('root.backoffice.cooperatives.store'), [
            ...$this->validCooperativeAttributes('Coopérative Root'),
            ...$this->validAddressAttributes(),
        ]);

        $response->assertSessionHasNoErrors();
        $cooperative = Cooperative::query()->where('name', 'Coopérative Root')->firstOrFail();

        $response->assertRedirect(route('root.backoffice.cooperatives.show', $cooperative));
        $this->assertDatabaseMissing('super_admin_cooperative', [
            'cooperative_id' => $cooperative->getKey(),
        ]);
    }

    public function test_super_admin_cannot_open_unassigned_cooperative_even_by_direct_url(): void
    {
        $cooperative = Cooperative::factory()->create();
        $actor = $this->actor('super_admin', ['cooperatives.view']);

        $this->actingAs($actor)
            ->get(route('backoffice.cooperatives.show', $cooperative))
            ->assertForbidden();
    }

    public function test_user_cannot_create_cooperative(): void
    {
        $actor = $this->actor('user', ['cooperatives.view', 'cooperatives.create']);

        $this->actingAs($actor)
            ->post(route('backoffice.cooperatives.store'), ['name' => 'Non autorisée'])
            ->assertForbidden();

        $this->assertDatabaseMissing('cooperatives', ['name' => 'Non autorisée']);
    }

    public function test_cooperative_admin_can_update_contact_fields_but_not_name(): void
    {
        $cooperative = Cooperative::factory()->create(['name' => 'Nom protégé']);
        $actor = $this->actor('cooperative_admin', [
            'cooperatives.view',
            'cooperatives.update',
        ], ['cooperative_id' => $cooperative->getKey()]);

        $this->actingAs($actor)
            ->put(route('backoffice.cooperatives.update', $cooperative), [
                'phone' => '+212611111111',
                'email' => 'updated@example.test',
            ])
            ->assertRedirect(route('backoffice.cooperatives.show', $cooperative));

        $this->assertDatabaseHas('cooperatives', [
            'id' => $cooperative->getKey(),
            'name' => 'Nom protégé',
            'phone' => '+212611111111',
            'email' => 'updated@example.test',
        ]);

        $this->actingAs($actor)
            ->put(route('backoffice.cooperatives.update', $cooperative), [
                'name' => 'Nom interdit',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('cooperatives', [
            'id' => $cooperative->getKey(),
            'name' => 'Nom interdit',
        ]);
    }

    public function test_assigned_super_admin_can_change_status_and_it_is_audited(): void
    {
        $cooperative = Cooperative::factory()->active()->create();
        $actor = $this->actor('super_admin', [
            'cooperatives.view',
            'cooperatives.update',
        ]);
        $actor->cooperatives()->attach($cooperative->getKey());

        $this->actingAs($actor)
            ->patch(route('backoffice.cooperatives.suspend', $cooperative))
            ->assertRedirect(route('backoffice.cooperatives.show', $cooperative));

        $this->assertDatabaseHas('cooperatives', [
            'id' => $cooperative->getKey(),
            'status' => CooperativeStatus::SUSPENDED->value,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'event_key' => 'cooperatives.suspended',
            'subject_id' => $cooperative->getKey(),
            'actor_id' => $actor->getKey(),
        ]);
    }

    public function test_unassigned_super_admin_cannot_delete_cooperative(): void
    {
        $cooperative = Cooperative::factory()->create();
        $actor = $this->actor('super_admin', ['cooperatives.view', 'cooperatives.delete']);

        $this->actingAs($actor)
            ->delete(route('backoffice.cooperatives.destroy', $cooperative))
            ->assertForbidden();

        $this->assertDatabaseHas('cooperatives', [
            'id' => $cooperative->getKey(),
            'deleted_at' => null,
        ]);
    }

    public function test_cooperative_specific_abilities_cannot_use_generic_permissions(): void
    {
        $cooperative = Cooperative::factory()->create();
        $actor = $this->actor('cooperative_admin', [
            'activate',
            'deactivate',
            'suspend',
            'archive',
            'restore',
        ], ['cooperative_id' => $cooperative->getKey()]);

        foreach (['activate', 'deactivate', 'suspend', 'archive', 'restore'] as $ability) {
            $this->assertFalse(
                Gate::forUser($actor)->allows($ability, $cooperative),
                sprintf('The generic "%s" permission must not bypass CooperativePolicy.', $ability),
            );
        }
    }

    public function test_root_can_access_its_independent_cooperative_index_and_create_views(): void
    {
        $actor = $this->actor('super_admin_root', ['cooperatives.view', 'cooperatives.create']);

        $this->actingAs($actor)
            ->get(route('root.backoffice.cooperatives.index'))
            ->assertOk()
            ->assertViewIs('super-admin-root.cooperatives.index');

        $this->morocco();

        $this->actingAs($actor)
            ->get(route('root.backoffice.cooperatives.create'))
            ->assertOk()
            ->assertViewIs('super-admin-root.cooperatives.create');
    }

    public function test_root_dashboard_and_navigation_use_only_root_cooperative_routes(): void
    {
        $actor = $this->actor('super_admin_root', ['cooperatives.view']);

        $dashboard = $this->actingAs($actor)
            ->get(route('root.backoffice.dashboard'));

        $dashboard->assertOk()
            ->assertViewIs('super-admin-root.dashboard')
            ->assertSee(route('root.backoffice.cooperatives.index'))
            ->assertDontSee(route('backoffice.cooperatives.index'));

        $index = $this->actingAs($actor)
            ->get(route('root.backoffice.cooperatives.index'));

        $index->assertOk()
            ->assertSee(route('root.backoffice.cooperatives.index'))
            ->assertDontSee(route('backoffice.cooperatives.index'));
    }

    public function test_super_admin_cannot_access_root_cooperative_management_routes(): void
    {
        $actor = $this->actor('super_admin', ['cooperatives.view']);

        $this->actingAs($actor)
            ->get(route('root.backoffice.cooperatives.index'))
            ->assertForbidden();
    }

    public function test_root_cooperative_management_routes_are_registered_separately(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('root.backoffice.cooperatives.index'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('root.backoffice.cooperatives.store'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('root.backoffice.cooperatives.destroy'));
    }

    public function test_index_requires_view_permission(): void
    {
        $actor = $this->actor('super_admin', ['cooperatives.create']);

        $this->actingAs($actor)
            ->get(route('backoffice.cooperatives.index'))
            ->assertForbidden();
    }

    public function test_root_can_update_all_five_cooperative_address_fields(): void
    {
        $cooperative = Cooperative::factory()->create();
        $actor = $this->actor('super_admin_root', ['cooperatives.view', 'cooperatives.update']);
        $country = $this->morocco();

        $this->actingAs($actor)
            ->put(route('root.backoffice.cooperatives.update', $cooperative), [
                'address_line_1' => '12 Avenue Hassan II',
                'city' => 'Rabat',
                'country_id' => $country->getKey(),
                'postal_code' => '10000',
                'state' => 'Rabat-Salé-Kénitra',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('root.backoffice.cooperatives.show', $cooperative));

        $this->assertDatabaseHas('addresses', [
            'id' => $cooperative->fresh()->address_id,
            'address_line_1' => '12 Avenue Hassan II',
            'city' => 'Rabat',
            'country_id' => $country->getKey(),
            'postal_code' => '10000',
            'state' => 'Rabat-Salé-Kénitra',
        ]);
    }

    public function test_root_create_form_lists_all_moroccan_regions_and_morocco(): void
    {
        $actor = $this->actor('super_admin_root', ['cooperatives.view', 'cooperatives.create']);

        $this->morocco();

        $this->actingAs($actor)
            ->get(route('root.backoffice.cooperatives.create'))
            ->assertOk()
            ->assertSee('Maroc')
            ->assertSee('Tanger-Tétouan-Al Hoceïma')
            ->assertSee("L&#039;Oriental", false)
            ->assertSee('Fès-Meknès')
            ->assertSee('Rabat-Salé-Kénitra')
            ->assertSee('Béni Mellal-Khénifra')
            ->assertSee('Casablanca-Settat')
            ->assertSee('Marrakech-Safi')
            ->assertSee('Drâa-Tafilalet')
            ->assertSee('Souss-Massa')
            ->assertSee('Guelmim-Oued Noun')
            ->assertSee('Laâyoune-Sakia El Hamra')
            ->assertSee('Dakhla-Oued Ed-Dahab');
    }

    public function test_root_cannot_select_a_country_other_than_morocco(): void
    {
        $actor = $this->actor('super_admin_root', ['cooperatives.view', 'cooperatives.create']);
        $foreignCountry = Country::factory()->create([
            'code' => 'ES',
            'iso3' => 'ESP',
            'name' => 'Espagne',
        ]);

        $this->actingAs($actor)
            ->post(route('root.backoffice.cooperatives.store'), [
                ...$this->validCooperativeAttributes('Pays interdit'),
                ...$this->validAddressAttributes(),
                'country_id' => $foreignCountry->getKey(),
            ])
            ->assertSessionHasErrors(['country_id']);

        $this->assertDatabaseMissing('cooperatives', ['name' => 'Pays interdit']);
    }

    public function test_root_cannot_create_cooperative_without_all_five_address_fields(): void
    {
        $actor = $this->actor('super_admin_root', ['cooperatives.view', 'cooperatives.create']);

        $this->actingAs($actor)
            ->post(route('root.backoffice.cooperatives.store'), [
                ...$this->validCooperativeAttributes('Adresse incomplète'),
                'address_line_1' => '1 Rue Test',
                'city' => 'Casablanca',
            ])
            ->assertSessionHasErrors(['country_id', 'postal_code', 'state']);

        $this->assertDatabaseMissing('cooperatives', ['name' => 'Adresse incomplète']);
    }

    private function morocco(): Country
    {
        return Country::query()->firstOrCreate(
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

    /** @return array<string, mixed> */
    private function validCooperativeAttributes(string $name): array
    {
        return [
            'name' => $name,
            'rib' => '123456789012345678901234',
            'tax_id' => 'TAX-HTTP',
            'address_id' => Address::factory()->create()->getKey(),
            'phone' => '+212600000001',
            'email' => strtolower(str_replace(' ', '-', $name)).'@example.test',
            'rc' => 'RC-HTTP',
            'ice' => '001234567890123',
        ];
    }

    /** @return array<string, mixed> */
    private function validAddressAttributes(): array
    {
        $country = Country::query()->firstOrCreate(
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

        return [
            'address_line_1' => '10 Avenue Hassan II',
            'city' => 'Casablanca',
            'country_id' => $country->getKey(),
            'postal_code' => '20000',
            'state' => 'Casablanca-Settat',
        ];
    }

    /** @param list<string> $permissions
     *  @param array<string, mixed> $attributes
     */
    private function actor(string $roleSlug, array $permissions, array $attributes = []): User
    {
        $actor = User::factory()->create($attributes);
        $role = Role::query()->firstOrCreate(
            ['slug' => $roleSlug],
            ['name' => str($roleSlug)->replace('_', ' ')->title()->toString(), 'is_active' => true],
        );

        foreach ($permissions as $slug) {
            $permission = Permission::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => str($slug)->replace('.', ' ')->title()->toString(), 'is_active' => true],
            );
            $role->permissions()->syncWithoutDetaching([$permission->getKey()]);
        }

        $actor->roles()->syncWithoutDetaching([$role->getKey()]);

        return $actor;
    }
}
