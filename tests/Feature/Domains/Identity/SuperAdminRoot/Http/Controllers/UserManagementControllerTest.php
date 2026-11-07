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
use Illuminate\Support\Facades\Hash;
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


    public function test_root_can_resend_manual_verification_invitation_to_active_unverified_user(): void
    {
        Notification::fake();

        $root = $this->actor('super_admin_root', ['users.create']);
        $target = User::factory()->create([
            'status' => UserStatus::Active,
            'email_verified_at' => null,
        ]);

        $oldInvitation = UserInvitation::query()->create([
            'id' => (string) \\Illuminate\\Support\\Str::ulid(),
            'user_id' => $target->getKey(),
            'purpose' => 'root_manual_verification',
            'token_hash' => hash('sha256', 'old-root-verification-token'),
            'expires_at' => now()->addMinutes(30),
            'created_at' => now(),
        ]);

        $this->actingAs($root)
            ->post(route('root.backoffice.users.resend-invitation', $target))
            ->assertRedirect(route('root.backoffice.users.show', $target))
            ->assertSessionHas('success');

        $oldInvitation->refresh();

        $this->assertNotNull($oldInvitation->revoked_at);

        $newInvitation = UserInvitation::query()
            ->where('user_id', $target->getKey())
            ->whereNull('revoked_at')
            ->whereNull('used_at')
            ->firstOrFail();

        $this->assertSame('root_manual_verification', $newInvitation->purpose);
        $this->assertSame(UserStatus::Active, $target->fresh()->status);
        $this->assertNull($target->fresh()->email_verified_at);

        Notification::assertSentOnDemand(UserInvitationNotification::class);
    }

    public function test_root_reactivation_activates_account_and_sends_verification_invitation(): void
    {
        Notification::fake();

        $root = $this->actor('super_admin_root', ['users.activate']);
        $target = User::factory()->create([
            'status' => UserStatus::Inactive,
            'email_verified_at' => null,
        ]);

        $oldInvitation = UserInvitation::query()->create([
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'user_id' => $target->getKey(),
            'token_hash' => hash('sha256', 'old-inactive-invitation'),
            'expires_at' => now()->addMinutes(30),
            'created_at' => now(),
        ]);

        $this->actingAs($root)
            ->patch(route('root.backoffice.users.activate', $target))
            ->assertRedirect(route('root.backoffice.users.show', $target))
            ->assertSessionHas('success');

        $this->assertSame(UserStatus::Active, $target->fresh()->status);
        $this->assertNull($target->fresh()->email_verified_at);
        $this->assertNotNull($oldInvitation->fresh()->revoked_at);

        $newInvitation = UserInvitation::query()
            ->where('user_id', $target->getKey())
            ->whereNull('revoked_at')
            ->whereNull('used_at')
            ->firstOrFail();

        $this->assertNotSame($oldInvitation->token_hash, $newInvitation->token_hash);
        $this->assertSame('root_manual_verification', $newInvitation->purpose);
        Notification::assertSentOnDemand(UserInvitationNotification::class);
        $this->assertDatabaseHas('audit_events', [
            'event_key' => 'identity.user.activated_for_email_verification',
            'actor_id' => $root->getKey(),
            'subject_id' => $target->getKey(),
        ]);
    }

    public function test_root_can_activate_and_verify_immediately_with_a_forced_password_change(): void
    {
        Notification::fake();

        $root = $this->actor('super_admin_root', ['users.activate']);
        $target = User::factory()->create([
            'status' => UserStatus::Suspended,
            'email_verified_at' => null,
        ]);
        $target->roles()->attach($this->ensureRole('user')->getKey());
        [$session, $token] = $this->createAccessCredentials($target);

        $this->actingAs($root)
            ->patch(route('root.backoffice.users.activate-and-verify', $target))
            ->assertRedirect(route('root.backoffice.users.show', $target))
            ->assertSessionHas('success');

        $target->refresh();
        $this->assertSame(UserStatus::Active, $target->status);
        $this->assertNotNull($target->email_verified_at);
        $this->assertTrue($target->must_change_password);
        $this->assertTrue(Hash::check('MahlineMahline', $target->password));
        $this->assertNotNull($session->fresh()->revoked_at);
        $this->assertNotNull($token->fresh()->revoked_at);
        Notification::assertNothingSent();

        $this->actingAs($target)
            ->get(route('password.force-change.edit'))
            ->assertOk()
            ->assertSee('Changer votre mot de passe');

        $this->actingAs($target)
            ->put(route('password.force-change.update'), [
                'password' => 'NewSecurePassword2026',
                'password_confirmation' => 'NewSecurePassword2026',
            ])
            ->assertRedirect(route('user.dashboard'));

        $target->refresh();
        $this->assertFalse($target->must_change_password);
        $this->assertTrue(Hash::check('NewSecurePassword2026', $target->password));
    }

    public function test_archived_user_must_be_restored_before_either_root_activation_flow(): void
    {
        $root = $this->actor('super_admin_root', ['users.activate']);
        $target = User::factory()->create([
            'status' => UserStatus::Archived,
            'email_verified_at' => null,
        ]);

        $this->actingAs($root)
            ->patch(route('root.backoffice.users.activate', $target))
            ->assertForbidden();

        $this->actingAs($root)
            ->patch(route('root.backoffice.users.activate-and-verify', $target))
            ->assertForbidden();

        $this->assertSame(UserStatus::Archived, $target->fresh()->status);
        $this->assertDatabaseMissing('user_invitations', [
            'user_id' => $target->getKey(),
        ]);
    }

    public function test_root_can_deactivate_an_active_user(): void
    {
        $root = $this->actor('super_admin_root', ['users.deactivate']);
        $target = User::factory()->create(['status' => UserStatus::Active]);
        [$session, $token] = $this->createAccessCredentials($target);
        $invitation = $this->createPendingInvitation($target, 'deactivation-token');

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
        $this->assertNotNull($invitation->fresh()->revoked_at);
    }

    public function test_root_can_suspend_an_active_user(): void
    {
        $root = $this->actor('super_admin_root', ['users.suspend']);
        $target = User::factory()->create(['status' => UserStatus::Active]);
        [$session, $token] = $this->createAccessCredentials($target);
        $invitation = $this->createPendingInvitation($target, 'suspension-token');

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
        $this->assertNotNull($invitation->fresh()->revoked_at);
    }

    public function test_root_can_archive_and_restore_a_user(): void
    {
        Notification::fake();

        $root = $this->actor('super_admin_root', [
            'users.archive',
            'users.restore',
        ]);
        $target = User::factory()->create(['status' => UserStatus::Active]);
        [$session, $token] = $this->createAccessCredentials($target);
        $invitation = $this->createPendingInvitation($target, 'archive-token');

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
        $this->assertNotNull($invitation->fresh()->revoked_at);

        $this->actingAs($root)
            ->patch(route('root.backoffice.users.restore', $target))
            ->assertRedirect(route('root.backoffice.users.show', $target))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $target->getKey(),
            'status' => UserStatus::Pending->value,
        ]);
        // Restoring never reactivates former invitations or credentials.
        $this->assertNotNull($session->fresh()->revoked_at);
        $this->assertNotNull($token->fresh()->revoked_at);
        $this->assertNotNull($invitation->fresh()->revoked_at);
        $this->assertDatabaseMissing('user_invitations', [
            'user_id' => $target->getKey(),
            'revoked_at' => null,
        ]);
        Notification::assertNothingSent();
    }

    public function test_root_can_soft_delete_and_restore_a_user(): void
    {
        Notification::fake();

        $root = $this->actor('super_admin_root', [
            'users.delete',
            'users.restore_deleted',
        ]);
        $target = User::factory()->create();
        [$session, $token] = $this->createAccessCredentials($target);
        $invitation = $this->createPendingInvitation($target, 'deleted-user-token');

        $this->actingAs($root)
            ->delete(route('root.backoffice.users.destroy', $target))
            ->assertRedirect(route('root.backoffice.users.index'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('users', ['id' => $target->getKey()]);
        $this->assertSame('user_deleted', $session->fresh()->revocation_reason);
        $this->assertNotNull($session->fresh()->revoked_at);
        $this->assertNotNull($token->fresh()->revoked_at);
        $this->assertNotNull($invitation->fresh()->revoked_at);

        $this->actingAs($root)
            ->patch(route('root.backoffice.users.restore-deleted', $target->getKey()))
            ->assertRedirect(route('root.backoffice.users.show', $target))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $target->getKey(),
            'deleted_at' => null,
            'status' => UserStatus::Pending->value,
            'email_verified_at' => null,
        ]);
        // Restoring a soft-deleted account leaves old sessions, tokens and invitations revoked.
        $this->assertNotNull($session->fresh()->revoked_at);
        $this->assertNotNull($token->fresh()->revoked_at);
        $this->assertNotNull($invitation->fresh()->revoked_at);
        $this->assertDatabaseMissing('user_invitations', [
            'user_id' => $target->getKey(),
            'revoked_at' => null,
        ]);
        Notification::assertNothingSent();
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

    public function test_root_can_update_user_profile_and_role(): void
    {
        $root = $this->actor('super_admin_root', ['users.view']);
        $target = User::factory()->create([
            'status' => UserStatus::Active,
            'first_name' => 'Ancien',
            'last_name' => 'Nom',
        ]);
        $target->roles()->attach($this->ensureRole('user')->getKey());
        $this->ensureRole('cooperative_admin');
        $cooperative = Cooperative::factory()->create();
        [$session, $token] = $this->createAccessCredentials($target);

        $this->actingAs($root)
            ->get(route('root.backoffice.users.edit', $target))
            ->assertOk()
            ->assertViewIs('super-admin-root.users.edit')
            ->assertSee('Modifier un utilisateur')
            ->assertDontSee('super_admin_root');

        $this->actingAs($root)
            ->put(route('root.backoffice.users.update', $target), [
                'first_name' => 'Nouveau',
                'last_name' => 'Nom',
                'email' => $target->email,
                'telephone' => '+212622222222',
                'role_slug' => 'cooperative_admin',
                'cooperative_id' => $cooperative->getKey(),
            ])
            ->assertRedirect(route('root.backoffice.users.show', $target))
            ->assertSessionHas('success');

        $target->refresh();
        $this->assertSame('Nouveau Nom', $target->display_name);
        $this->assertSame('+212622222222', $target->telephone);
        $this->assertSame((string) $cooperative->getKey(), (string) $target->cooperative_id);
        $this->assertSame(['cooperative_admin'], $target->roles()->pluck('slug')->all());
        $this->assertNotNull($session->fresh()->revoked_at);
        $this->assertNotNull($token->fresh()->revoked_at);
    }

    public function test_root_email_change_requires_verification_and_sends_new_invitation(): void
    {
        Notification::fake();

        $root = $this->actor('super_admin_root', ['users.view']);
        $cooperative = Cooperative::factory()->create();
        $target = User::factory()->create([
            'status' => UserStatus::Active,
            'cooperative_id' => $cooperative->getKey(),
        ]);
        $target->roles()->attach($this->ensureRole('user')->getKey());

        $this->actingAs($root)
            ->put(route('root.backoffice.users.update', $target), [
                'first_name' => $target->first_name,
                'last_name' => $target->last_name,
                'email' => 'changed-address@example.test',
                'telephone' => $target->telephone,
                'role_slug' => 'user',
                'cooperative_id' => $cooperative->getKey(),
            ])
            ->assertRedirect(route('root.backoffice.users.show', $target))
            ->assertSessionHas('success');

        $target->refresh();
        $this->assertSame('changed-address@example.test', $target->email);
        $this->assertNull($target->email_verified_at);
        $this->assertDatabaseHas('user_invitations', [
            'user_id' => $target->getKey(),
            'used_at' => null,
            'revoked_at' => null,
        ]);
        Notification::assertSentOnDemand(UserInvitationNotification::class);
    }

    public function test_root_cannot_edit_a_root_account_even_when_it_is_inactive(): void
    {
        $root = $this->actor('super_admin_root', ['users.view']);
        $protectedRoot = User::factory()->create(['status' => UserStatus::Inactive]);
        $protectedRoot->roles()->attach($this->ensureRole('super_admin_root')->getKey());

        $this->actingAs($root)
            ->get(route('root.backoffice.users.edit', $protectedRoot))
            ->assertNotFound();

        $this->actingAs($root)
            ->put(route('root.backoffice.users.update', $protectedRoot), [
                'first_name' => 'Intrusion',
                'last_name' => 'Test',
                'email' => $protectedRoot->email,
                'telephone' => $protectedRoot->telephone,
                'role_slug' => 'user',
                'cooperative_id' => Cooperative::factory()->create()->getKey(),
            ])
            ->assertNotFound();

        $this->assertSame('super_admin_root', $protectedRoot->roles()->firstOrFail()->slug);
    }

    public function test_root_cannot_assign_root_role_through_user_edit(): void
    {
        $root = $this->actor('super_admin_root', ['users.view']);
        $target = User::factory()->create();
        $target->roles()->attach($this->ensureRole('user')->getKey());

        $this->actingAs($root)
            ->put(route('root.backoffice.users.update', $target), [
                'first_name' => $target->first_name,
                'last_name' => $target->last_name,
                'email' => $target->email,
                'telephone' => $target->telephone,
                'role_slug' => 'super_admin_root',
            ])
            ->assertSessionHasErrors('role_slug');

        $this->assertSame(['user'], $target->fresh()->roles()->pluck('slug')->all());
    }

    public function test_root_can_manage_super_admin_cooperative_assignments_in_root_workspace(): void
    {
        $root = $this->actor('super_admin_root', [
            'users.view',
            'users.assign_super_admin',
            'users.unassign_super_admin',
        ]);
        $superAdmin = User::factory()->create(['status' => UserStatus::Active]);
        $superAdmin->roles()->attach($this->ensureRole('super_admin')->getKey());
        $cooperative = Cooperative::factory()->create();

        $this->actingAs($root)
            ->get(route('root.backoffice.users.cooperatives.index', $superAdmin))
            ->assertOk()
            ->assertViewIs('super-admin-root.users.cooperatives')
            ->assertSee('Affectations des coopératives')
            ->assertSee($cooperative->name);

        $this->actingAs($root)
            ->post(route('root.backoffice.users.cooperatives.store', [
                'superAdmin' => $superAdmin,
                'cooperative' => $cooperative,
            ]))
            ->assertRedirect(route('root.backoffice.users.cooperatives.index', $superAdmin))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('super_admin_cooperative', [
            'super_admin_id' => $superAdmin->getKey(),
            'cooperative_id' => $cooperative->getKey(),
        ]);

        $this->actingAs($root)
            ->delete(route('root.backoffice.users.cooperatives.destroy', [
                'superAdmin' => $superAdmin,
                'cooperative' => $cooperative,
            ]))
            ->assertRedirect(route('root.backoffice.users.cooperatives.index', $superAdmin))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('super_admin_cooperative', [
            'super_admin_id' => $superAdmin->getKey(),
            'cooperative_id' => $cooperative->getKey(),
        ]);
    }

    /**
     * @return array{AuthenticationSession, \App\Domains\Identity\Authentication\Models\PersonalAccessToken}
     */
    private function createPendingInvitation(User $user, string $token): UserInvitation
    {
        return UserInvitation::query()->create([
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'user_id' => $user->getKey(),
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addHour(),
            'created_at' => now(),
        ]);
    }

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
            ->assertSee('Utilisateur Root')
            ->assertSee('Déconnexion');
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
