<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Identity\UserManagement\Http\Controllers;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authorization\Models\Permission;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Authentication\Http\Middleware\EnsureActiveAuthenticationSession;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UserManagementControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureActiveAuthenticationSession::class);
    }

    public function test_root_sees_users_with_and_without_cooperative_assignments(): void
    {
        $root = $this->actor('super_admin_root', ['users.view']);
        $cooperative = Cooperative::factory()->create(['name' => 'Coopérative visible']);
        $assignedUser = User::factory()->create([
            'display_name' => 'Utilisateur affecté',
            'cooperative_id' => $cooperative->getKey(),
        ]);
        $unassignedSuperAdmin = $this->actor('super_admin', [], [
            'display_name' => 'Super admin non affecté',
        ]);
        $userWithoutCooperative = User::factory()->create([
            'display_name' => 'Utilisateur sans coopérative',
            'cooperative_id' => null,
        ]);

        $response = $this->actingAs($root)
            ->get(route('backoffice.users.index'));

        $response->assertOk()
            ->assertViewIs('backoffice.users.index')
            ->assertSee('Utilisateur affecté')
            ->assertSee('Super admin non affecté')
            ->assertSee('Utilisateur sans coopérative')
            ->assertSee('Coopérative visible')
            ->assertSee('Aucune coopérative');
    }

    public function test_root_can_filter_users_by_search_role_and_status(): void
    {
        $root = $this->actor('super_admin_root', ['users.view']);
        $target = $this->actor('super_admin', [], [
            'first_name' => 'Amina',
            'last_name' => 'Test',
            'display_name' => 'Amina Test',
            'email' => 'amina@example.test',
            'status' => UserStatus::Inactive,
        ]);
        $this->actor('user', [], ['display_name' => 'Autre utilisateur']);

        $this->actingAs($root)
            ->get(route('backoffice.users.index', [
                'search' => 'amina',
                'role' => 'super_admin',
                'status' => UserStatus::Inactive->value,
            ]))
            ->assertOk()
            ->assertSee('Amina Test')
            ->assertDontSee('Autre utilisateur');
    }

    public function test_non_root_user_is_forbidden_from_global_user_list(): void
    {
        $actor = $this->actor('super_admin', ['users.view']);

        $this->actingAs($actor)
            ->get(route('backoffice.users.index'))
            ->assertForbidden();
    }

    public function test_user_without_users_view_permission_is_forbidden(): void
    {
        $root = $this->actor('super_admin_root', []);

        $this->actingAs($root)
            ->get(route('backoffice.users.index'))
            ->assertForbidden();
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
