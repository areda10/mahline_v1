<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Identity\UserManagement\Http\Controllers;

use App\Domains\Identity\Authorization\Models\Permission;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Authentication\Http\Middleware\EnsureActiveAuthenticationSession;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Cooperatives\Models\Cooperative;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SuperAdminCooperativeAssignmentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Le middleware de session MAHLINE est couvert par ses tests dédiés.
        $this->withoutMiddleware(EnsureActiveAuthenticationSession::class);
    }

    public function test_root_can_view_assigned_and_available_cooperatives(): void
    {
        $root = $this->actor('super_admin_root', ['users.view']);
        $superAdmin = $this->actor('super_admin', []);
        $assigned = Cooperative::factory()->create(['name' => 'Coopérative affectée']);
        $available = Cooperative::factory()->create(['name' => 'Coopérative disponible']);
        $superAdmin->cooperatives()->attach($assigned->getKey());

        $this->actingAs($root)
            ->get(route('backoffice.super-admins.cooperatives.index', $superAdmin))
            ->assertOk()
            ->assertViewIs('backoffice.super-admins.cooperatives.index')
            ->assertSee('Coopérative affectée')
            ->assertSee('Coopérative disponible');
    }

    public function test_super_admin_can_only_see_its_own_assignments_and_no_unassigned_cooperative_details(): void
    {
        $superAdmin = $this->actor('super_admin', ['users.view']);
        $assigned = Cooperative::factory()->create([
            'name' => 'Coopérative autorisée',
            'email' => 'autorisee@example.test',
        ]);
        $unassigned = Cooperative::factory()->create([
            'name' => 'Coopérative confidentielle',
            'email' => 'confidentielle@example.test',
        ]);

        $superAdmin->cooperatives()->attach($assigned->getKey());

        $this->actingAs($superAdmin)
            ->get(route('backoffice.super-admins.cooperatives.index', $superAdmin))
            ->assertOk()
            ->assertSee('Coopérative autorisée')
            ->assertSee('autorisee@example.test')
            ->assertDontSee('Coopérative confidentielle')
            ->assertDontSee('confidentielle@example.test');
    }

    public function test_root_can_assign_an_active_super_admin_and_the_action_is_audited(): void
    {
        $root = $this->actor('super_admin_root', ['users.view', 'users.assign_super_admin']);
        $superAdmin = $this->actor('super_admin', []);
        $cooperative = Cooperative::factory()->create();

        $this->actingAs($root)
            ->post(route('backoffice.super-admins.cooperatives.store', [$superAdmin, $cooperative]))
            ->assertRedirect(route('backoffice.super-admins.cooperatives.index', $superAdmin))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('super_admin_cooperative', [
            'super_admin_id' => $superAdmin->getKey(),
            'cooperative_id' => $cooperative->getKey(),
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_key' => 'identity.super_admin.cooperative_assigned',
            'actor_id' => $root->getKey(),
            'subject_id' => $superAdmin->getKey(),
            'cooperative_id' => $cooperative->getKey(),
        ]);
    }

    public function test_repeated_assignment_does_not_duplicate_the_pivot_or_audit_event(): void
    {
        $root = $this->actor('super_admin_root', ['users.view', 'users.assign_super_admin']);
        $superAdmin = $this->actor('super_admin', []);
        $cooperative = Cooperative::factory()->create();
        $url = route('backoffice.super-admins.cooperatives.store', [$superAdmin, $cooperative]);

        $this->actingAs($root)->post($url)->assertRedirect();
        $this->actingAs($root)->post($url)->assertRedirect();

        $this->assertSame(
            1,
            $superAdmin->cooperatives()->whereKey($cooperative->getKey())->count(),
        );

        $this->assertDatabaseCount('audit_events', 1);
        $this->assertDatabaseHas('audit_events', [
            'event_key' => 'identity.super_admin.cooperative_assigned',
            'subject_id' => $superAdmin->getKey(),
            'cooperative_id' => $cooperative->getKey(),
        ]);
    }

    public function test_assignment_domain_conflict_returns_a_controlled_error(): void
    {
        $root = $this->actor('super_admin_root', ['users.view', 'users.assign_super_admin']);
        $superAdmin = $this->actor('super_admin', []);
        $cooperative = Cooperative::factory()->create();

        $this->mock(\\App\\Domains\\Identity\\UserManagement\\Services\\UserManagementService::class)
            ->shouldReceive('assignSuperAdminToCooperative')
            ->once()
            ->andThrow(new \\DomainException('Target status changed.'));

        $this->actingAs($root)
            ->post(route('backoffice.super-admins.cooperatives.store', [$superAdmin, $cooperative]))
            ->assertRedirect(route('backoffice.super-admins.cooperatives.index', $superAdmin))
            ->assertSessionHas('error');
    }

    public function test_assignment_database_conflict_returns_a_controlled_error(): void
    {
        $root = $this->actor('super_admin_root', ['users.view', 'users.assign_super_admin']);
        $superAdmin = $this->actor('super_admin', []);
        $cooperative = Cooperative::factory()->create();
        $exception = new \\Illuminate\\Database\\QueryException(
            'mysql',
            'insert into super_admin_cooperative',
            [],
            new \\PDOException('Duplicate assignment.'),
        );

        $this->mock(\\App\\Domains\\Identity\\UserManagement\\Services\\UserManagementService::class)
            ->shouldReceive('assignSuperAdminToCooperative')
            ->once()
            ->andThrow($exception);

        $this->actingAs($root)
            ->post(route('backoffice.super-admins.cooperatives.store', [$superAdmin, $cooperative]))
            ->assertRedirect(route('backoffice.super-admins.cooperatives.index', $superAdmin))
            ->assertSessionHas('error');
    }

    public function test_unassignment_domain_conflict_returns_a_controlled_error(): void
    {
        $root = $this->actor('super_admin_root', ['users.view', 'users.unassign_super_admin']);
        $superAdmin = $this->actor('super_admin', []);
        $cooperative = Cooperative::factory()->create();
        $superAdmin->cooperatives()->attach($cooperative->getKey());

        $this->mock(\\App\\Domains\\Identity\\UserManagement\\Services\\UserManagementService::class)
            ->shouldReceive('removeSuperAdminFromCooperative')
            ->once()
            ->andThrow(new \\DomainException('Target changed.'));

        $this->actingAs($root)
            ->delete(route('backoffice.super-admins.cooperatives.destroy', [$superAdmin, $cooperative]))
            ->assertRedirect(route('backoffice.super-admins.cooperatives.index', $superAdmin))
            ->assertSessionHas('error');
    }

    public function test_unassignment_database_conflict_returns_a_controlled_error(): void
    {
        $root = $this->actor('super_admin_root', ['users.view', 'users.unassign_super_admin']);
        $superAdmin = $this->actor('super_admin', []);
        $cooperative = Cooperative::factory()->create();
        $superAdmin->cooperatives()->attach($cooperative->getKey());
        $exception = new \\Illuminate\\Database\\QueryException(
            'mysql',
            'delete from super_admin_cooperative',
            [],
            new \\PDOException('Database conflict.'),
        );

        $this->mock(\\App\\Domains\\Identity\\UserManagement\\Services\\UserManagementService::class)
            ->shouldReceive('removeSuperAdminFromCooperative')
            ->once()
            ->andThrow($exception);

        $this->actingAs($root)
            ->delete(route('backoffice.super-admins.cooperatives.destroy', [$superAdmin, $cooperative]))
            ->assertRedirect(route('backoffice.super-admins.cooperatives.index', $superAdmin))
            ->assertSessionHas('error');
    }

    public function test_root_can_unassign_a_suspended_super_admin_and_the_action_is_audited(): void
    {
        $root = $this->actor('super_admin_root', ['users.view', 'users.unassign_super_admin']);
        $superAdmin = $this->actor('super_admin', [], ['status' => UserStatus::Suspended]);
        $cooperative = Cooperative::factory()->create();
        $superAdmin->cooperatives()->attach($cooperative->getKey());

        $this->actingAs($root)
            ->delete(route('backoffice.super-admins.cooperatives.destroy', [$superAdmin, $cooperative]))
            ->assertRedirect(route('backoffice.super-admins.cooperatives.index', $superAdmin))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('super_admin_cooperative', [
            'super_admin_id' => $superAdmin->getKey(),
            'cooperative_id' => $cooperative->getKey(),
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_key' => 'identity.super_admin.cooperative_unassigned',
            'actor_id' => $root->getKey(),
            'subject_id' => $superAdmin->getKey(),
            'cooperative_id' => $cooperative->getKey(),
        ]);
    }

    public function test_inactive_super_admin_cannot_receive_a_new_assignment(): void
    {
        $root = $this->actor('super_admin_root', ['users.view', 'users.assign_super_admin']);
        $superAdmin = $this->actor('super_admin', [], ['status' => UserStatus::Inactive]);
        $cooperative = Cooperative::factory()->create();

        $this->actingAs($root)
            ->post(route('backoffice.super-admins.cooperatives.store', [$superAdmin, $cooperative]))
            ->assertForbidden();

        $this->assertDatabaseMissing('super_admin_cooperative', [
            'super_admin_id' => $superAdmin->getKey(),
            'cooperative_id' => $cooperative->getKey(),
        ]);
    }

    public function test_non_root_roles_cannot_view_another_super_admin_assignments_or_manage_them(): void
    {
        $target = $this->actor('super_admin', []);
        $cooperative = Cooperative::factory()->create();

        foreach (['super_admin', 'cooperative_admin', 'user'] as $roleSlug) {
            $actor = $this->actor($roleSlug, ['users.view', 'users.assign_super_admin', 'users.unassign_super_admin']);

            $this->actingAs($actor)
                ->get(route('backoffice.super-admins.cooperatives.index', $target))
                ->assertForbidden();

            $this->actingAs($actor)
                ->post(route('backoffice.super-admins.cooperatives.store', [$target, $cooperative]))
                ->assertForbidden();

            $this->actingAs($actor)
                ->delete(route('backoffice.super-admins.cooperatives.destroy', [$target, $cooperative]))
                ->assertForbidden();
        }
    }

    public function test_root_cannot_assign_a_user_without_the_super_admin_role(): void
    {
        $root = $this->actor('super_admin_root', ['users.view', 'users.assign_super_admin']);
        $target = $this->actor('user', []);
        $cooperative = Cooperative::factory()->create();

        $this->actingAs($root)
            ->post(route('backoffice.super-admins.cooperatives.store', [$target, $cooperative]))
            ->assertForbidden();

        $this->assertDatabaseMissing('super_admin_cooperative', [
            'super_admin_id' => $target->getKey(),
            'cooperative_id' => $cooperative->getKey(),
        ]);
    }

    /**
     * @param list<string> $permissions
     * @param array<string, mixed> $attributes
     */
    private function actor(string $roleSlug, array $permissions, array $attributes = []): User
    {
        $actor = User::factory()->create($attributes);

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
