<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Identity\SuperAdminRoot\Http\Controllers;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authorization\Models\Permission;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Authentication\Http\Middleware\EnsureActiveAuthenticationSession;
use App\Domains\Identity\Authentication\Models\AuthenticationSession;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Identity\UserManagement\Models\UserInvitation;
use App\Domains\Identity\UserManagement\Notifications\UserInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class UserManagementControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureActiveAuthenticationSession::class);
    }


    public function test_root_can_create_super_admin_without_cooperative_and_send_invitation(): void
    {
        Notification::fake();

        $root = $this->actor('super_admin_root', ['users.create']);
        $this->ensureRole('super_admin');

        $payload = $this->validCreationPayload('super_admin');

        $this->actingAs($root)
            ->post(route('root.backoffice.users.store'), $payload)
            ->assertRedirect(route('root.backoffice.users.index'))
            ->assertSessionHas('success');

        $created = User::query()->where('email', $payload['email'])->firstOrFail();

        $this->assertSame(UserStatus::Pending, $created->status);
        $this->assertNull($created->cooperative_id);
        $this->assertNull($created->email_verified_at);
        $this->assertTrue($created->roles()->where('slug', 'super_admin')->exists());

        $invitation = UserInvitation::query()
            ->where('user_id', $created->getKey())
            ->firstOrFail();

        $this->assertNull($invitation->used_at);
        $this->assertNull($invitation->revoked_at);
        Notification::assertSentOnDemand(UserInvitationNotification::class);
    }

    public function test_root_can_create_cooperative_admin_and_user_in_selected_cooperative(): void
    {
        Notification::fake();

        $root = $this->actor('super_admin_root', ['users.create']);
        $cooperative = Cooperative::factory()->create();

        foreach (['cooperative_admin', 'user'] as $roleSlug) {
            $this->ensureRole($roleSlug);
            $payload = $this->validCreationPayload($roleSlug, $cooperative->getKey());

            $this->actingAs($root)
                ->post(route('root.backoffice.users.store'), $payload)
                ->assertRedirect(route('root.backoffice.users.index'))
                ->assertSessionHas('success');

            $created = User::query()->where('email', $payload['email'])->firstOrFail();

            $this->assertSame(UserStatus::Pending, $created->status);
            $this->assertSame((string) $cooperative->getKey(), (string) $created->cooperative_id);
            $this->assertTrue($created->roles()->where('slug', $roleSlug)->exists());

            $this->assertDatabaseHas('user_invitations', [
                'user_id' => $created->getKey(),
                'used_at' => null,
                'revoked_at' => null,
            ]);
        }

        Notification::assertSentOnDemand(UserInvitationNotification::class);
    }

    public function test_root_cannot_assign_super_admin_root_role_through_user_creation_form(): void
    {
        Notification::fake();

        $root = $this->actor('super_admin_root', ['users.create']);
        $payload = $this->validCreationPayload('super_admin_root');

        $this->actingAs($root)
            ->post(route('root.backoffice.users.store'), $payload)
            ->assertSessionHasErrors('role_slug');

        $this->assertDatabaseMissing('users', ['email' => $payload['email']]);
        $this->assertDatabaseCount('user_invitations', 0);
        Notification::assertNothingSent();
    }


    public function test_root_can_activate_a_pending_user(): void
    {
        $root = $this->actor('super_admin_root', ['users.activate']);
        $target = User::factory()->create(['status' => UserStatus::Pending]);

        $this->actingAs($root)
            ->patch(route('root.backoffice.users.activate', $target))
            ->assertRedirect(route('root.backoffice.users.show', $target))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $target->getKey(),
            'status' => UserStatus::Active->value,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'event_key' => 'identity.user.status_changed',
            'actor_id' => $root->getKey(),
            'subject_id' => $target->getKey(),
        ]);
    }

    public function test_root_can_deactivate_an_active_user(): void
    {
        $root = $this->actor('super_admin_root', ['users.deactivate']);
        $target = User::factory()->create(['status' => UserStatus::Active]);
        [$session, $token] = $this->createAccessCredentials($target);

        $this->actingAs($root)
            ->patch(route('root.backoffice.users.deactivate', $target))
            ->assertRedirect(route('root.backoffice.users.show', $target))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $target->getKey(),
            'status' => UserStatus::Inactive->value,
        ]);
        $this->assertDatabaseHas('authentication_sessions', [
            'id' => $session->getKey(),
            'revocation_reason' => 'user_deactivated',
        ]);
        $this->assertNotNull($session->fresh()->revoked_at);
        $this->assertNotNull($token->fresh()->revoked_at);
    }

    public function test_root_can_suspend_an_active_user(): void
    {
        $root = $this->actor('super_admin_root', ['users.suspend']);
        $target = User::factory()->create(['status' => UserStatus::Active]);
        [$session, $token] = $this->createAccessCredentials($target);

        $this->actingAs($root)
            ->patch(route('root.backoffice.users.suspend', $target))
            ->assertRedirect(route('root.backoffice.users.show', $target))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $target->getKey(),
            'status' => UserStatus::Suspended->value,
        ]);
        $this->assertSame('user_suspended', $session->fresh()->revocation_reason);
        $this->assertNotNull($session->fresh()->revoked_at);
        $this->assertNotNull($token->fresh()->revoked_at);
    }

    public function test_root_can_archive_and_restore_a_user(): void
    {
        $root = $this->actor('super_admin_root', [
            'users.archive',
            'users.restore',
        ]);
        $target = User::factory()->create(['status' => UserStatus::Active]);
        [$session, $token] = $this->createAccessCredentials($target);

        $this->actingAs($root)
            ->patch(route('root.backoffice.users.archive', $target))
            ->assertRedirect(route('root.backoffice.users.show', $target))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $target->getKey(),
            'status' => UserStatus::Archived->value,
        ]);
        $this->assertSame('user_archived', $session->fresh()->revocation_reason);
        $this->assertNotNull($session->fresh()->revoked_at);
        $this->assertNotNull($token->fresh()->revoked_at);

        $this->actingAs($root)
            ->patch(route('root.backoffice.users.restore', $target))
            ->assertRedirect(route('root.backoffice.users.show', $target))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $target->getKey(),
            'status' => UserStatus::Active->value,
        ]);
        // Restoring the account must never reactivate its former credentials.
        $this->assertNotNull($session->fresh()->revoked_at);
        $this->assertNotNull($token->fresh()->revoked_at);
    }

    public function test_root_can_soft_delete_and_restore_a_user(): void
    {
        $root = $this->actor('super_admin_root', [
            'users.delete',
            'users.restore_deleted',
        ]);
        $target = User::factory()->create();
        [$session, $token] = $this->createAccessCredentials($target);

        $this->actingAs($root)
            ->delete(route('root.backoffice.users.destroy', $target))
            ->assertRedirect(route('root.backoffice.users.index'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('users', ['id' => $target->getKey()]);
        $this->assertSame('user_deleted', $session->fresh()->revocation_reason);
        $this->assertNotNull($session->fresh()->revoked_at);
        $this->assertNotNull($token->fresh()->revoked_at);

        $this->actingAs($root)
            ->patch(route('root.backoffice.users.restore-deleted', $target->getKey()))
            ->assertRedirect(route('root.backoffice.users.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $target->getKey(),
            'deleted_at' => null,
        ]);
        // Restoring a soft-deleted account leaves old sessions and API tokens revoked.
        $this->assertNotNull($session->fresh()->revoked_at);
        $this->assertNotNull($token->fresh()->revoked_at);
    }

    public function test_super_admin_cannot_use_root_lifecycle_routes(): void
    {
        $superAdmin = $this->actor('super_admin', [
            'users.activate',
            'users.deactivate',
            'users.suspend',
            'users.archive',
            'users.restore',
            'users.delete',
            'users.restore_deleted',
        ]);
        $pending = User::factory()->create(['status' => UserStatus::Pending]);
        $active = User::factory()->create(['status' => UserStatus::Active]);
        $archived = User::factory()->create(['status' => UserStatus::Archived]);

        $this->actingAs($superAdmin)
            ->patch(route('root.backoffice.users.activate', $pending))
            ->assertForbidden();
        $this->actingAs($superAdmin)
            ->patch(route('root.backoffice.users.deactivate', $active))
            ->assertForbidden();
        $this->actingAs($superAdmin)
            ->patch(route('root.backoffice.users.suspend', $active))
            ->assertForbidden();
        $this->actingAs($superAdmin)
            ->patch(route('root.backoffice.users.archive', $active))
            ->assertForbidden();
        $this->actingAs($superAdmin)
            ->patch(route('root.backoffice.users.restore', $archived))
            ->assertForbidden();
        $this->actingAs($superAdmin)
            ->delete(route('root.backoffice.users.destroy', $active))
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $pending->getKey(),
            'status' => UserStatus::Pending->value,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $active->getKey(),
            'status' => UserStatus::Active->value,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $archived->getKey(),
            'status' => UserStatus::Archived->value,
        ]);
    }

    /**
     * @return array{AuthenticationSession, \App\Domains\Identity\Authentication\Models\PersonalAccessToken}
     */
    private function createAccessCredentials(User $user): array
    {
        $session = AuthenticationSession::query()->create([
            'user_id' => $user->getKey(),
            'session_id' => 'test-session-'.str()->random(40),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'MAHLINE feature test',
            'browser' => 'Test Browser',
            'device' => 'Test Device',
            'authenticated_at' => now(),
            'last_activity_at' => now(),
        ]);

        $token = $user->createToken('MAHLINE feature test')->accessToken;

        return [$session, $token];
    }

    /**
     * @return array<string, string>
     */
    private function validCreationPayload(
        string $roleSlug,
        string|int|null $cooperativeId = null,
        UserStatus $status = UserStatus::Pending,
    ): array {
        return [
            'first_name' => 'Amina',
            'last_name' => 'Test',
            'email' => fake()->unique()->safeEmail(),
            'telephone' => '+212600000000',
            'role_slug' => $roleSlug,
            'status' => $status->value,
            ...($cooperativeId === null ? [] : [
                'cooperative_id' => (string) $cooperativeId,
            ]),
        ];
    }

    private function ensureRole(string $slug): Role
    {
        return Role::query()->firstOrCreate(
            ['slug' => $slug],
            [
                'name' => str($slug)->replace('_', ' ')->title()->toString(),
                'is_active' => true,
            ],
        );
    }

    public function test_root_can_open_its_dedicated_user_list(): void
    {
        $root = $this->actor('super_admin_root', ['users.view']);
        $target = User::factory()->create(['display_name' => 'Utilisateur Root']);

        $this->actingAs($root)
            ->get(route('root.backoffice.users.index'))
            ->assertOk()
            ->assertViewIs('super-admin-root.users.index')
            ->assertSee('Navigation Root')
            ->assertDontSee('Navigation principale')
            ->assertSee('Utilisateur Root');
    }

    public function test_root_can_open_its_dedicated_user_creation_form(): void
    {
        $root = $this->actor('super_admin_root', ['users.create']);
        Cooperative::factory()->create(['name' => 'Coopérative Root']);

        Role::query()->firstOrCreate(
            ['slug' => 'super_admin'],
            ['name' => 'Super Admin', 'is_active' => true],
        );

        $this->actingAs($root)
            ->get(route('root.backoffice.users.create'))
            ->assertOk()
            ->assertViewIs('super-admin-root.users.create')
            ->assertSee('Navigation Root')
            ->assertDontSee('Navigation principale')
            ->assertSee('Coopérative Root')
            ->assertViewHas('roles', fn ($roles) => $roles->contains('slug', 'super_admin'));
    }

    public function test_root_can_open_user_detail_in_its_dedicated_space(): void
    {
        $root = $this->actor('super_admin_root', ['users.view']);
        $target = User::factory()->create(['display_name' => 'Utilisateur détaillé Root']);

        $this->actingAs($root)
            ->get(route('root.backoffice.users.show', $target))
            ->assertOk()
            ->assertViewIs('super-admin-root.users.show')
            ->assertSee('Navigation Root')
            ->assertDontSee('Navigation principale')
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
