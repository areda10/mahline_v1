<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Categories\Views;

use App\Domains\Categories\Models\Category;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

final class CategoryViewsTest extends TestCase
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

    public function test_category_index_view_renders(): void
    {
        Category::factory()
            ->forCooperative($this->cooperative)
            ->create(['name' => 'Catégorie de test']);

        $this->get(route('backoffice.categories.index', [
            'cooperative' => $this->cooperative->getKey(),
        ]))
            ->assertOk()
            ->assertViewIs('backoffice.categories.index')
            ->assertSee('Catégorie de test')
            ->assertSee('Créer une catégorie');
    }

    public function test_category_create_view_renders_parent_selector(): void
    {
        Category::factory()->global()->create([
            'name' => 'Catégorie globale',
        ]);

        $this->get(route('backoffice.categories.create', [
            'cooperative' => $this->cooperative->getKey(),
        ]))
            ->assertOk()
            ->assertViewIs('backoffice.categories.create')
            ->assertSee('Catégorie globale')
            ->assertSee('parent_id')
            ->assertSee('Créer la catégorie');
    }

    public function test_category_edit_view_renders_existing_values(): void
    {
        $category = Category::factory()
            ->forCooperative($this->cooperative)
            ->create(['name' => 'Catégorie à modifier']);

        $this->get(route('backoffice.categories.edit', [
            'cooperative' => $this->cooperative->getKey(),
            'category' => $category->getKey(),
        ]))
            ->assertOk()
            ->assertViewIs('backoffice.categories.edit')
            ->assertSee('Catégorie à modifier')
            ->assertSee('Enregistrer');
    }
}