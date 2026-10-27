<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Catalog\Http\Controllers;

use App\Domains\Catalog\Models\Catalog;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

final class CatalogControllerTest extends TestCase
{
    use RefreshDatabase;

    private Cooperative $cooperative;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cooperative = Cooperative::factory()
            ->active()
            ->create();

        $user = User::factory()->create([
            'cooperative_id' => $this->cooperative->getKey(),
        ]);

        $this->actingAs($user);

        // Ces tests isolent le comportement du contrôleur.
        // Les règles d'autorisation doivent être testées séparément.
        Gate::before(static fn (): bool => true);
    }

    public function test_catalog_index_returns_successful_response(): void
    {
        $response = $this->get(route(
            'backoffice.catalogs.index',
            ['cooperative' => $this->cooperative->getKey()],
        ));

        $response->assertOk()
            ->assertViewIs('backoffice.catalogs.index');
    }

    public function test_catalog_can_be_created(): void
    {
        $response = $this->post(route(
            'backoffice.catalogs.store',
            ['cooperative' => $this->cooperative->getKey()],
        ), [
            'name' => 'Catalogue Bien-être',
            'description' => 'Produits de bien-être',
            'is_active' => true,
        ]);

        $response->assertRedirectToRoute(
            'backoffice.catalogs.index',
            ['cooperative' => $this->cooperative->getKey()],
        );

        $this->assertDatabaseHas('catalogs', [
            'cooperative_id' => $this->cooperative->getKey(),
            'name' => 'Catalogue Bien-être',
            'slug' => 'catalogue-bien-etre',
            'is_active' => true,
            'deleted_at' => null,
        ]);
    }

    public function test_catalog_creation_requires_a_name(): void
    {
        $response = $this->from(route(
            'backoffice.catalogs.create',
            ['cooperative' => $this->cooperative->getKey()],
        ))->post(route(
            'backoffice.catalogs.store',
            ['cooperative' => $this->cooperative->getKey()],
        ), [
            'description' => 'Sans nom',
        ]);

        $response->assertRedirect()
            ->assertSessionHasErrors(['name']);

        $this->assertDatabaseMissing('catalogs', [
            'cooperative_id' => $this->cooperative->getKey(),
            'description' => 'Sans nom',
        ]);
    }

    public function test_catalog_can_be_deleted_without_deleting_its_record_physically(): void
    {
        $catalog = Catalog::factory()->create([
            'cooperative_id' => $this->cooperative->getKey(),
        ]);

        $response = $this->delete(route(
            'backoffice.catalogs.destroy',
            [
                'cooperative' => $this->cooperative->getKey(),
                'catalog' => $catalog->getKey(),
            ],
        ));

        $response->assertRedirectToRoute(
            'backoffice.catalogs.index',
            ['cooperative' => $this->cooperative->getKey()],
        );

        $this->assertSoftDeleted('catalogs', [
            'id' => $catalog->getKey(),
        ]);
    }

    public function test_catalog_from_another_cooperative_is_not_found(): void
    {
        $otherCooperative = Cooperative::factory()
            ->active()
            ->create();

        $foreignCatalog = Catalog::factory()->create([
            'cooperative_id' => $otherCooperative->getKey(),
        ]);

        $response = $this->get(route(
            'backoffice.catalogs.show',
            [
                'cooperative' => $this->cooperative->getKey(),
                'catalog' => $foreignCatalog->getKey(),
            ],
        ));

        $response->assertNotFound();
    }
}