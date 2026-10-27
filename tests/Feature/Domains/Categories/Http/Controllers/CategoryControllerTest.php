<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Categories\Http\Controllers;

use App\Domains\Categories\Models\Category;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

final class CategoryControllerTest extends TestCase
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

        // Isole le contrôleur ; ne remplace pas les tests de Policy.
        Gate::before(static fn (): bool => true);
    }

    public function test_category_index_returns_successful_response(): void
    {
        $response = $this->get(route(
            'backoffice.categories.index',
            ['cooperative' => $this->cooperative->getKey()],
        ));

        $response->assertOk()
            ->assertViewIs('backoffice.categories.index');
    }

    public function test_local_category_can_be_created(): void
    {
        $response = $this->post(route(
            'backoffice.categories.store',
            ['cooperative' => $this->cooperative->getKey()],
        ), [
            'name' => 'Fruits secs',
            'description' => 'Catégorie de fruits secs',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $response->assertRedirectToRoute(
            'backoffice.categories.index',
            ['cooperative' => $this->cooperative->getKey()],
        );

        $this->assertDatabaseHas('categories', [
            'cooperative_id' => $this->cooperative->getKey(),
            'name' => 'Fruits secs',
            'slug' => 'fruits-secs',
            'is_active' => true,
            'deleted_at' => null,
        ]);
    }

    public function test_category_creation_requires_a_name(): void
    {
        $response = $this->from(route(
            'backoffice.categories.create',
            ['cooperative' => $this->cooperative->getKey()],
        ))->post(route(
            'backoffice.categories.store',
            ['cooperative' => $this->cooperative->getKey()],
        ), [
            'description' => 'Catégorie sans nom',
        ]);

        $response->assertRedirect()
            ->assertSessionHasErrors(['name']);

        $this->assertDatabaseMissing('categories', [
            'cooperative_id' => $this->cooperative->getKey(),
            'description' => 'Catégorie sans nom',
        ]);
    }

    public function test_category_can_be_updated(): void
    {
        $category = Category::factory()
            ->forCooperative($this->cooperative)
            ->create([
                'name' => 'Ancien nom',
                'slug' => 'ancien-nom',
            ]);

        $response = $this->patch(route(
            'backoffice.categories.update',
            [
                'cooperative' => $this->cooperative->getKey(),
                'category' => $category->getKey(),
            ],
        ), [
            'name' => 'Nouvelle catégorie',
        ]);

        $response->assertRedirectToRoute(
            'backoffice.categories.index',
            ['cooperative' => $this->cooperative->getKey()],
        );

        $this->assertDatabaseHas('categories', [
            'id' => $category->getKey(),
            'cooperative_id' => $this->cooperative->getKey(),
            'name' => 'Nouvelle catégorie',
            'slug' => 'nouvelle-categorie',
            'deleted_at' => null,
        ]);
    }

    public function test_category_can_be_deleted_without_physical_deletion(): void
    {
        $category = Category::factory()
            ->forCooperative($this->cooperative)
            ->create();

        $response = $this->delete(route(
            'backoffice.categories.destroy',
            [
                'cooperative' => $this->cooperative->getKey(),
                'category' => $category->getKey(),
            ],
        ));

        $response->assertRedirectToRoute(
            'backoffice.categories.index',
            ['cooperative' => $this->cooperative->getKey()],
        );

        $this->assertSoftDeleted('categories', [
            'id' => $category->getKey(),
        ]);
    }

    public function test_category_from_another_cooperative_is_not_found(): void
    {
        $otherCooperative = Cooperative::factory()
            ->active()
            ->create();

        $foreignCategory = Category::factory()
            ->forCooperative($otherCooperative)
            ->create();

        $response = $this->get(route(
            'backoffice.categories.edit',
            [
                'cooperative' => $this->cooperative->getKey(),
                'category' => $foreignCategory->getKey(),
            ],
        ));

        $response->assertNotFound();
    }
}