<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Catalog\Views;

use App\Domains\Catalog\Models\Catalog;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

final class CatalogViewsTest extends TestCase
{
    use RefreshDatabase;

    private Cooperative $cooperative;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cooperative = Cooperative::factory()
            ->active()
            ->create();

        $this->actingAs(User::factory()->create([
            'cooperative_id' => $this->cooperative->getKey(),
        ]));

        Gate::before(static fn (): bool => true);
    }

    public function test_catalog_index_view_renders(): void
    {
        Catalog::factory()->create([
            'cooperative_id' => $this->cooperative->getKey(),
            'name' => 'Catalogue de test',
        ]);

        $this->get(route('backoffice.catalogs.index', [
            'cooperative' => $this->cooperative->getKey(),
        ]))
            ->assertOk()
            ->assertViewIs('backoffice.catalogs.index')
            ->assertSee('Catalogue de test')
            ->assertSee('Créer un catalogue');
    }

    public function test_catalog_create_view_renders_form(): void
    {
        $this->get(route('backoffice.catalogs.create', [
            'cooperative' => $this->cooperative->getKey(),
        ]))
            ->assertOk()
            ->assertViewIs('backoffice.catalogs.create')
            ->assertSee('name')
            ->assertSee('description')
            ->assertSee('Créer le catalogue');
    }

    public function test_catalog_edit_view_renders_existing_values(): void
    {
        $catalog = Catalog::factory()->create([
            'cooperative_id' => $this->cooperative->getKey(),
            'name' => 'Catalogue à modifier',
        ]);

        $this->get(route('backoffice.catalogs.edit', [
            'cooperative' => $this->cooperative->getKey(),
            'catalog' => $catalog->getKey(),
        ]))
            ->assertOk()
            ->assertViewIs('backoffice.catalogs.edit')
            ->assertSee('Catalogue à modifier')
            ->assertSee('Enregistrer');
    }

    public function test_catalog_show_view_renders_associated_products_section(): void
    {
        $catalog = Catalog::factory()->create([
            'cooperative_id' => $this->cooperative->getKey(),
            'name' => 'Catalogue détaillé',
        ]);

        $this->get(route('backoffice.catalogs.show', [
            'cooperative' => $this->cooperative->getKey(),
            'catalog' => $catalog->getKey(),
        ]))
            ->assertOk()
            ->assertViewIs('backoffice.catalogs.show')
            ->assertSee('Catalogue détaillé')
            ->assertSee('Produits associés')
            ->assertSee('product_id');
    }
}