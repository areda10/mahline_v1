<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Categories\Http\Controllers;

use App\Domains\Categories\Models\Category;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Authentication\Http\Middleware\EnsureActiveAuthenticationSession;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

final class GlobalCategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureActiveAuthenticationSession::class);
        Gate::before(static fn (): bool => true);
    }

    public function test_root_can_open_global_categories_without_cooperative_context(): void
    {
        $root = $this->userWithRole('super_admin_root');

        Category::query()->create([
            'cooperative_id' => null,
            'name' => 'Bien-être',
            'slug' => 'bien-etre',
            'is_active' => true,
        ]);

        $this->actingAs($root)
            ->get(route('root.backoffice.categories.index'))
            ->assertOk()
            ->assertViewIs('super-admin-root.categories.index')
            ->assertSee('Bien-être')
            ->assertSee('sans sélection de coopérative');
    }

    public function test_root_can_create_a_global_category(): void
    {
        $root = $this->userWithRole('super_admin_root');

        $this->actingAs($root)
            ->post(route('root.backoffice.categories.store'), [
                'name' => 'Cosmétiques',
                'description' => 'Catégorie globale',
                'is_active' => '1',
                'sort_order' => 2,
            ])
            ->assertRedirect(route('root.backoffice.categories.index'));

        $this->assertDatabaseHas('categories', [
            'cooperative_id' => null,
            'name' => 'Cosmétiques',
            'slug' => 'cosmetiques',
            'is_active' => true,
            'deleted_at' => null,
        ]);
    }

    public function test_non_root_user_cannot_open_global_categories(): void
    {
        $user = $this->userWithRole('super_admin');

        $this->actingAs($user)
            ->get(route('root.backoffice.categories.index'))
            ->assertForbidden();
    }

    private function userWithRole(string $slug): User
    {
        $user = User::factory()->create();

        $role = Role::query()->create([
            'name' => str($slug)->replace('_', ' ')->title()->toString(),
            'slug' => $slug,
            'is_active' => true,
        ]);

        $user->roles()->attach($role->getKey());

        return $user;
    }
}
