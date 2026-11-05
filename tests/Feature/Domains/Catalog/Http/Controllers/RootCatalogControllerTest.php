<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Catalog\Http\Controllers;

use App\Domains\Catalog\Models\Catalog;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authentication\Http\Middleware\EnsureActiveAuthenticationSession;
use App\Domains\Identity\Authorization\Models\Permission;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RootCatalogControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(EnsureActiveAuthenticationSession::class);
    }

    public function test_root_can_list_catalogs_for_the_selected_cooperative(): void
    {
        $cooperative = Cooperative::factory()->active()->create();
        $catalog = Catalog::factory()->create([
            'cooperative_id' => $cooperative->getKey(),
            'name' => 'Catalogue Root visible',
        ]);
        $actor = $this->actor('super_admin_root', ['cooperatives.view', 'catalogs.view']);

        $this->actingAs($actor)
            ->get(route('root.backoffice.cooperatives.catalogs.index', $cooperative))
            ->assertOk()
            ->assertViewIs('super-admin-root.cooperatives.catalogs.index')
            ->assertSee($catalog->name);
    }

    public function test_root_can_create_catalog_for_selected_cooperative(): void
    {
        $cooperative = Cooperative::factory()->active()->create();
        $actor = $this->actor('super_admin_root', ['cooperatives.view', 'catalogs.create']);

        $this->actingAs($actor)
            ->post(route('root.backoffice.cooperatives.catalogs.store', $cooperative), [
                'name' => 'Catalogue créé par Root',
                'description' => 'Catalogue de test',
                'is_active' => 1,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('root.backoffice.cooperatives.catalogs.index', $cooperative));

        $this->assertDatabaseHas('catalogs', [
            'cooperative_id' => $cooperative->getKey(),
            'name' => 'Catalogue créé par Root',
            'slug' => 'catalogue-cree-par-root',
            'is_active' => true,
            'deleted_at' => null,
        ]);
    }

    public function test_root_can_update_its_cooperative_catalog(): void
    {
        $cooperative = Cooperative::factory()->active()->create();
        $catalog = Catalog::factory()->create(['cooperative_id' => $cooperative->getKey()]);
        $actor = $this->actor('super_admin_root', ['cooperatives.view', 'catalogs.update']);

        $this->actingAs($actor)
            ->put(route('root.backoffice.cooperatives.catalogs.update', [
                'cooperative' => $cooperative->getKey(),
                'catalog' => $catalog->getKey(),
            ]), ['name' => 'Catalogue modifié'])
            ->assertRedirect(route('root.backoffice.cooperatives.catalogs.index', $cooperative));

        $this->assertDatabaseHas('catalogs', [
            'id' => $catalog->getKey(),
            'name' => 'Catalogue modifié',
        ]);
    }

    public function test_root_can_soft_delete_catalog(): void
    {
        $cooperative = Cooperative::factory()->active()->create();
        $catalog = Catalog::factory()->create(['cooperative_id' => $cooperative->getKey()]);
        $actor = $this->actor('super_admin_root', ['cooperatives.view', 'catalogs.delete']);

        $this->actingAs($actor)
            ->delete(route('root.backoffice.cooperatives.catalogs.destroy', [
                'cooperative' => $cooperative->getKey(),
                'catalog' => $catalog->getKey(),
            ]))
            ->assertRedirect(route('root.backoffice.cooperatives.catalogs.index', $cooperative));

        $this->assertSoftDeleted('catalogs', ['id' => $catalog->getKey()]);
    }

    public function test_super_admin_cannot_access_root_catalog_routes(): void
    {
        $cooperative = Cooperative::factory()->active()->create();
        $actor = $this->actor('super_admin', ['cooperatives.view', 'catalogs.view']);

        $this->actingAs($actor)
            ->get(route('root.backoffice.cooperatives.catalogs.index', $cooperative))
            ->assertForbidden();
    }

    public function test_root_cannot_access_catalog_through_another_cooperative_scope(): void
    {
        $cooperative = Cooperative::factory()->active()->create();
        $otherCooperative = Cooperative::factory()->active()->create();
        $catalog = Catalog::factory()->create(['cooperative_id' => $otherCooperative->getKey()]);
        $actor = $this->actor('super_admin_root', ['cooperatives.view', 'catalogs.view']);

        $this->actingAs($actor)
            ->get(route('root.backoffice.cooperatives.catalogs.show', [
                'cooperative' => $cooperative->getKey(),
                'catalog' => $catalog->getKey(),
            ]))
            ->assertNotFound();
    }

    private function actor(string $roleSlug, array $permissions): User
    {
        $actor = User::factory()->create();
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
