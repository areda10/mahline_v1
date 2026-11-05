<?php

declare(strict_types=1);

namespace Tests\\Feature\\Domains\\Categories\\Http\\Controllers;

use App\\Domains\\Categories\\Models\\Category;
use App\\Domains\\Cooperatives\\Models\\Cooperative;
use App\\Domains\\Identity\\Authentication\\Http\\Middleware\\EnsureActiveAuthenticationSession;
use App\\Domains\\Identity\\Authorization\\Models\\Permission;
use App\\Domains\\Identity\\Authorization\\Models\\Role;
use App\\Domains\\Identity\\Users\\Models\\User;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Tests\\TestCase;

final class RootCooperativeCategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(EnsureActiveAuthenticationSession::class);
    }

    public function test_root_can_list_global_and_selected_cooperative_categories(): void
    {
        $cooperative = Cooperative::factory()->create();
        $global = Category::factory()->global()->create(['name' => 'Globale visible']);
        $local = Category::factory()->forCooperative($cooperative)->create(['name' => 'Locale visible']);
        $actor = $this->actor('super_admin_root', ['cooperatives.view', 'categories.view']);

        $this->actingAs($actor)
            ->get(route('root.backoffice.cooperatives.categories.index', $cooperative))
            ->assertOk()
            ->assertViewIs('super-admin-root.cooperatives.categories.index')
            ->assertSee($global->name)
            ->assertSee($local->name);
    }

    public function test_root_can_create_local_category_with_global_parent(): void
    {
        $cooperative = Cooperative::factory()->create();
        $global = Category::factory()->global()->create();
        $actor = $this->actor('super_admin_root', ['cooperatives.view', 'categories.view', 'categories.create']);

        $response = $this->actingAs($actor)
            ->post(route('root.backoffice.cooperatives.categories.store', $cooperative), [
                'name' => 'Locale créée par Root',
                'parent_id' => $global->getKey(),
                'is_active' => 1,
                'sort_order' => 2,
            ]);

        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('root.backoffice.cooperatives.categories.index', $cooperative));

        $this->assertDatabaseHas('categories', [
            'name' => 'Locale créée par Root',
            'cooperative_id' => $cooperative->getKey(),
            'parent_id' => $global->getKey(),
        ]);
    }

    public function test_super_admin_cannot_access_root_cooperative_category_routes(): void
    {
        $cooperative = Cooperative::factory()->create();
        $actor = $this->actor('super_admin', [
            'cooperatives.view',
            'categories.view',
            'categories.create',
        ]);

        $this->actingAs($actor)
            ->get(route('root.backoffice.cooperatives.categories.index', $cooperative))
            ->assertForbidden();
    }

    public function test_root_cannot_manage_local_category_through_another_cooperative_scope(): void
    {
        $cooperative = Cooperative::factory()->create();
        $otherCooperative = Cooperative::factory()->create();
        $category = Category::factory()->forCooperative($otherCooperative)->create();
        $actor = $this->actor('super_admin_root', ['cooperatives.view', 'categories.view', 'categories.update']);

        $this->actingAs($actor)
            ->get(route('root.backoffice.cooperatives.categories.edit', [
                'cooperative' => $cooperative->getKey(),
                'category' => $category->getKey(),
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
