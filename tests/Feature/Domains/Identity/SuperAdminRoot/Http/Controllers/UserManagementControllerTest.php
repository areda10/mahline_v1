<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Identity\SuperAdminRoot\Http\Controllers;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authorization\Models\Permission;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Authentication\Http\Middleware\EnsureActiveAuthenticationSession;
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

    public function test_root_can_open_its_dedicated_user_list(): void
    {
        $root = $this->actor('super_admin_root', ['users.view']);
        $target = User::factory()->create(['display_name' => 'Utilisateur Root']);

        $this->actingAs($root)
            ->get(route('root.backoffice.users.index'))
            ->assertOk()
            ->assertViewIs('super-admin-root.users.index')
            ->assertSee('Utilisateur Root');
    }

    public function test_root_can_open_its_dedicated_user_creation_form(): void
    {
        $root = $this->actor('super_admin_root', ['users.create']);
        Cooperative::factory()->create(['name' => 'Coopérative Root']);

        $this->actingAs($root)
            ->get(route('root.backoffice.users.create'))
            ->assertOk()
            ->assertViewIs('super-admin-root.users.create')
            ->assertSee('Coopérative Root')
            ->assertSee('super_admin');
    }

    public function test_root_can_open_user_detail_in_its_dedicated_space(): void
    {
        $root = $this->actor('super_admin_root', ['users.view']);
        $target = User::factory()->create(['display_name' => 'Utilisateur détaillé Root']);

        $this->actingAs($root)
            ->get(route('root.backoffice.users.show', $target))
            ->assertOk()
            ->assertViewIs('super-admin-root.users.show')
            ->assertSee('Utilisateur détaillé Root');
    }

    public function test_super_admin_cannot_access_root_user_management_routes_directly(): void
    {
        $superAdmin = $this->actor('super_admin', [
            'users.view',
            'users.create',
            'users.activate',
            'users.deactivate',
            'users.suspend',
        ]);
        $target = User::factory()->create();

        $this->actingAs($superAdmin)
            ->get(route('root.backoffice.users.index'))
            ->assertForbidden();

        $this->actingAs($superAdmin)
            ->get(route('root.backoffice.users.create'))
            ->assertForbidden();

        $this->actingAs($superAdmin)
            ->get(route('root.backoffice.users.show', $target))
            ->assertForbidden();
    }

    /**
     * @param list<string> $permissions
     */
    private function actor(string $roleSlug, array $permissions): User
    {
        $actor = User::factory()->create();

        $role = Role::query()->firstOrCreate(
            ['slug' => $roleSlug],
            [
                'name' => str($roleSlug)->replace('_', ' ')->title()->toString(),
                'is_active' => true,
            ],
        );

        foreach ($permissions as $slug) {
            $permission = Permission::query()->firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => str($slug)->replace('.', ' ')->title()->toString(),
                    'is_active' => true,
                ],
            );

            $role->permissions()->syncWithoutDetaching([$permission->getKey()]);
        }

        $actor->roles()->syncWithoutDetaching([$role->getKey()]);

        return $actor;
    }
}
