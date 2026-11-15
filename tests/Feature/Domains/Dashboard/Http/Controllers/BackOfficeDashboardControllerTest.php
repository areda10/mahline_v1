<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Dashboard\Http\Controllers;

use App\Domains\Cooperatives\Enums\CooperativeStatus;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authentication\Http\Middleware\EnsureActiveAuthenticationSession;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BackOfficeDashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureActiveAuthenticationSession::class);
    }

    public function test_root_cannot_view_the_regular_backoffice_dashboard(): void
    {
        $root = $this->userWithRole('super_admin_root');

        $this->actingAs($root)
            ->get(route('backoffice.dashboard'))
            ->assertForbidden();
    }

    public function test_super_admin_can_view_its_dedicated_backoffice_dashboard(): void
    {
        $user = $this->userWithRole('super_admin');

        $this->actingAs($user)
            ->get(route('backoffice.dashboard'))
            ->assertOk()
            ->assertViewIs('super-admin.dashboard');
    }

    public function test_root_can_view_its_dedicated_dashboard(): void
    {
        $root = $this->userWithRole('super_admin_root');

        $this->actingAs($root)
            ->get(route('root.backoffice.dashboard'))
            ->assertOk()
            ->assertViewIs('super-admin-root.dashboard')
            ->assertSee('Vue globale de la plateforme MAHLINE');
    }

    public function test_regular_user_can_view_its_personal_dashboard(): void
    {
        $user = $this->userWithRole('user');

        $this->actingAs($user)
            ->get(route('user.dashboard'))
            ->assertOk()
            ->assertViewIs('user.dashboard')
            ->assertSee('Votre espace personnel MAHLINE')
            ->assertSee($user->email);
    }

    public function test_non_user_role_cannot_view_regular_user_dashboard(): void
    {
        $superAdmin = $this->userWithRole('super_admin');

        $this->actingAs($superAdmin)
            ->get(route('user.dashboard'))
            ->assertForbidden();
    }

    public function test_non_root_user_cannot_view_dedicated_root_dashboard(): void
    {
        $user = $this->userWithRole('super_admin');

        $this->actingAs($user)
            ->get(route('root.backoffice.dashboard'))
            ->assertForbidden();
    }

    public function test_cooperative_admin_cannot_access_root_or_user_workspaces(): void
    {
        $cooperativeAdmin = $this->userWithRole('cooperative_admin');

        $this->actingAs($cooperativeAdmin)
            ->get(route('root.backoffice.dashboard'))
            ->assertForbidden();

        $this->actingAs($cooperativeAdmin)
            ->get(route('user.dashboard'))
            ->assertForbidden();
    }

    public function test_regular_user_cannot_access_backoffice_workspace(): void
    {
        $user = $this->userWithRole('user');

        $this->actingAs($user)
            ->get(route('backoffice.dashboard'))
            ->assertForbidden();
    }

    public function test_super_admin_cannot_access_root_workspace_even_with_root_permissions(): void
    {
        $superAdmin = $this->userWithRole('super_admin');

        $this->actingAs($superAdmin)
            ->get(route('root.backoffice.dashboard'))
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

    public function test_guest_cannot_view_dedicated_root_dashboard(): void
    {
        $this->get(route('root.backoffice.dashboard'))
            ->assertRedirect(route('authentication.login'));
    }

    public function test_root_dashboard_returns_global_statistics(): void
    {
        $root = $this->userWithRole('super_admin_root');

        Cooperative::factory()->active()->create();

        User::factory()->create([
            'status' => UserStatus::Pending,
        ]);

        $this->actingAs($root)
            ->get(route('root.backoffice.dashboard'))
            ->assertOk()
            ->assertViewHas('cooperativeTotal', 1)
            ->assertViewHas(
                'cooperativeStatuses',
                fn ($statuses): bool => $statuses[CooperativeStatus::ACTIVE->value] === 1,
            )
            ->assertViewHas('userTotal', 2)
            ->assertViewHas(
                'userStatuses',
                fn ($statuses): bool => $statuses[UserStatus::Pending->value] === 1,
            )
            ->assertViewHas(
                'usersByRole',
                fn ($roles): bool => $roles['super_admin_root'] === 1,
            );
    }

    public function test_root_dashboard_displays_only_root_navigation(): void
    {
        $root = $this->userWithRole('super_admin_root');

        $this->actingAs($root)
            ->get(route('root.backoffice.dashboard'))
            ->assertOk()
            ->assertSee('Navigation Root', false)
            ->assertDontSee('Navigation principale', false)
            ->assertSee('Tableau de bord')
            ->assertSee('Catégories globales')
            ->assertSee('Coopératives')
            ->assertSee('Utilisateurs')
            ->assertDontSee('Coopérative :');
    }
}
