<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Identity\UserManagement\Http\Controllers;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authorization\Models\Permission;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Authentication\Http\Middleware\EnsureActiveAuthenticationSession;
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
            'email_verified_at' => null,
        ]);

        $response = $this->actingAs($root)
            ->get(route('root.backoffice.users.index'));

        $response->assertOk()
            ->assertViewIs('super-admin-root.users.index')
            ->assertSee('Utilisateur affecté')
            ->assertSee('Super admin non affecté')
            ->assertSee('Utilisateur sans coopérative')
            ->assertSee('Coopérative visible')
            ->assertSee('Aucune coopérative')
            ->assertSee('Vérification email')
            ->assertSee('Vérifié')
            ->assertSee('À vérifier');
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
            ->get(route('root.backoffice.users.index', [
                'search' => 'amina',
                'role' => 'super_admin',
                'status' => UserStatus::Inactive->value,
            ]))
            ->assertOk()
            ->assertSee('Amina Test')
            ->assertDontSee('Autre utilisateur');
    }

    public function test_root_can_view_user_detail_and_main_cooperative(): void
    {
        $root = $this->actor('super_admin_root', ['users.view']);
        $cooperative = Cooperative::factory()->create(['name' => 'Coopérative détail']);
        $target = User::factory()->create([
            'display_name' => 'Utilisateur détaillé',
            'cooperative_id' => $cooperative->getKey(),
        ]);

        $this->actingAs($root)
            ->get(route('root.backoffice.users.show', $target))
            ->assertOk()
            ->assertViewIs('super-admin-root.users.show')
            ->assertSee('Utilisateur détaillé')
            ->assertSee('Coopérative détail');
    }

    public function test_root_sees_resend_invitation_button_for_pending_user(): void
    {
        $root = $this->actor('super_admin_root', ['users.view', 'users.create']);
        $target = User::factory()->create([
            'status' => UserStatus::Pending,
            'email_verified_at' => null,
        ]);

        $this->actingAs($root)
            ->get(route('root.backoffice.users.show', $target))
            ->assertOk()
            ->assertSee('Renvoyer l’invitation');
    }

    public function test_assigned_super_admin_sees_resend_invitation_button_for_pending_user(): void
    {
        $cooperative = Cooperative::factory()->create();

        $actor = $this->actor('super_admin', ['users.view', 'users.create']);
        $actor->cooperatives()->attach($cooperative->getKey());

        $target = User::factory()->create([
            'cooperative_id' => $cooperative->getKey(),
            'status' => UserStatus::Pending,
            'email_verified_at' => null,
        ]);

        $this->actingAs($actor)
            ->get(route('backoffice.users.show', $target))
            ->assertOk()
            ->assertSee('Renvoyer l’invitation');
    }

    public function test_assigned_super_admin_cannot_resend_verification_for_an_active_unverified_user(): void
    {
        $cooperative = Cooperative::factory()->create();
        $actor = $this->actor('super_admin', ['users.view', 'users.create']);
        $actor->cooperatives()->attach($cooperative->getKey());

        $target = User::factory()->create([
            'cooperative_id' => $cooperative->getKey(),
            'status' => UserStatus::Active,
            'email_verified_at' => null,
        ]);

        $this->actingAs($actor)
            ->post(route('backoffice.users.resend-invitation', $target))
            ->assertForbidden();

        $this->assertDatabaseMissing('user_invitations', [
            'user_id' => $target->getKey(),
        ]);
    }

    public function test_root_does_not_see_resend_invitation_button_for_active_user(): void
    {
        $root = $this->actor('super_admin_root', ['users.view', 'users.create']);
        $target = User::factory()->create([
            'status' => UserStatus::Active,
        ]);

        $this->actingAs($root)
            ->get(route('root.backoffice.users.show', $target))
            ->assertOk()
            ->assertDontSee('Renvoyer l’invitation');
    }

    public function test_cooperative_admin_does_not_see_resend_invitation_button(): void
    {
        $cooperative = Cooperative::factory()->create();

        $actor = $this->actor('cooperative_admin', ['users.view', 'users.create'], [
            'cooperative_id' => $cooperative->getKey(),
        ]);

        $target = User::factory()->create([
            'cooperative_id' => $cooperative->getKey(),
            'status' => UserStatus::Pending,
            'email_verified_at' => null,
        ]);

        $this->actingAs($actor)
            ->get(route('backoffice.users.show', $target))
            ->assertOk()
            ->assertDontSee('Renvoyer l’invitation');
    }

    public function test_root_can_view_super_admin_cooperative_assignments(): void
    {
        $root = $this->actor('super_admin_root', ['users.view']);
        $superAdmin = $this->actor('super_admin', [], [
            'display_name' => 'Administrateur global',
        ]);
        $first = Cooperative::factory()->create(['name' => 'Coopérative Alpha']);
        $second = Cooperative::factory()->create(['name' => 'Coopérative Beta']);
        $superAdmin->cooperatives()->attach([
            $first->getKey(),
            $second->getKey(),
        ]);

        $this->actingAs($root)
            ->get(route('root.backoffice.users.show', $superAdmin))
            ->assertOk()
            ->assertSee('Administrateur global')
            ->assertSee('Affectations aux coopératives')
            ->assertSee('Coopérative Alpha')
            ->assertSee('Coopérative Beta');
    }

    public function test_root_can_view_user_detail_without_cooperative(): void
    {
        $root = $this->actor('super_admin_root', ['users.view']);
        $target = User::factory()->create([
            'display_name' => 'Compte indépendant',
            'cooperative_id' => null,
        ]);

        $this->actingAs($root)
            ->get(route('root.backoffice.users.show', $target))
            ->assertOk()
            ->assertSee('Compte indépendant')
            ->assertSee('Aucune coopérative');
    }

    public function test_assigned_super_admin_can_view_user_detail_in_assigned_cooperative(): void
    {
        $cooperative = Cooperative::factory()->create();
        $actor = $this->actor('super_admin', ['users.view']);
        $actor->cooperatives()->attach($cooperative->getKey());
        $target = User::factory()->create([
            'display_name' => 'Utilisateur du périmètre',
            'cooperative_id' => $cooperative->getKey(),
        ]);

        $this->actingAs($actor)
            ->get(route('backoffice.users.show', $target))
            ->assertOk()
            ->assertSee('Utilisateur du périmètre');
    }

    public function test_super_admin_cannot_view_user_detail_outside_assigned_cooperatives(): void
    {
        $assigned = Cooperative::factory()->create();
        $unassigned = Cooperative::factory()->create();
        $actor = $this->actor('super_admin', ['users.view']);
        $actor->cooperatives()->attach($assigned->getKey());
        $target = User::factory()->create(['cooperative_id' => $unassigned->getKey()]);

        $this->actingAs($actor)
            ->get(route('backoffice.users.show', $target))
            ->assertForbidden();
    }

    public function test_cooperative_admin_can_view_user_detail_in_own_cooperative(): void
    {
        $cooperative = Cooperative::factory()->create();
        $actor = $this->actor('cooperative_admin', ['users.view'], [
            'cooperative_id' => $cooperative->getKey(),
        ]);
        $target = User::factory()->create([
            'display_name' => 'Utilisateur de ma coopérative',
            'cooperative_id' => $cooperative->getKey(),
        ]);

        $this->actingAs($actor)
            ->get(route('backoffice.users.show', $target))
            ->assertOk()
            ->assertSee('Utilisateur de ma coopérative');
    }

    public function test_cooperative_admin_cannot_view_user_detail_from_another_cooperative(): void
    {
        $own = Cooperative::factory()->create();
        $other = Cooperative::factory()->create();
        $actor = $this->actor('cooperative_admin', ['users.view'], [
            'cooperative_id' => $own->getKey(),
        ]);
        $target = User::factory()->create(['cooperative_id' => $other->getKey()]);

        $this->actingAs($actor)
            ->get(route('backoffice.users.show', $target))
            ->assertForbidden();
    }

    public function test_root_without_users_view_permission_cannot_view_user_detail(): void
    {
        $root = $this->actor('super_admin_root', []);
        $target = User::factory()->create();

        $this->actingAs($root)
            ->get(route('root.backoffice.users.show', $target))
            ->assertForbidden();
    }

    public function test_missing_user_detail_returns_not_found(): void
    {
        $root = $this->actor('super_admin_root', ['users.view']);

        $this->actingAs($root)
            ->get(route('root.backoffice.users.show', '01J00000000000000000000000'))
            ->assertNotFound();
    }

    public function test_root_can_activate_a_pending_user_from_the_detail_page(): void
    {
        Notification::fake();

        $root = $this->actor('super_admin_root', ['users.view', 'users.activate']);
        $target = User::factory()->create([
            'display_name' => 'Compte à activer',
            'status' => UserStatus::Pending,
            'email_verified_at' => null,
        ]);

        $this->actingAs($root)
            ->patch(route('root.backoffice.users.activate', $target))
            ->assertRedirect(route('root.backoffice.users.show', $target))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $target->getKey(),
            'status' => UserStatus::Active->value,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_key' => 'identity.user.activated_for_email_verification',
            'actor_id' => $root->getKey(),
            'subject_id' => $target->getKey(),
        ]);

        $this->assertDatabaseHas('user_invitations', [
            'user_id' => $target->getKey(),
            'purpose' => 'root_manual_verification',
            'used_at' => null,
            'revoked_at' => null,
        ]);
        Notification::assertSentOnDemand(UserInvitationNotification::class);
    }

    public function test_super_admin_cannot_activate_pending_or_inactive_users_even_in_assigned_cooperative(): void
    {
        $cooperative = Cooperative::factory()->create();
        $actor = $this->actor('super_admin', ['users.view', 'users.activate']);
        $actor->cooperatives()->attach($cooperative->getKey());

        foreach ([UserStatus::Pending, UserStatus::Inactive] as $status) {
            $target = User::factory()->create([
                'cooperative_id' => $cooperative->getKey(),
                'status' => $status,
            ]);

            $this->actingAs($actor)
                ->patch(route('backoffice.users.activate', $target))
                ->assertForbidden();

            $this->assertDatabaseHas('users', [
                'id' => $target->getKey(),
                'status' => $status->value,
            ]);
        }
    }

    public function test_root_can_activate_a_suspended_user(): void
    {
        $root = $this->actor('super_admin_root', ['users.view', 'users.activate']);
        $target = User::factory()->create([
            'status' => UserStatus::Suspended,
        ]);

        $this->actingAs($root)
            ->patch(route('root.backoffice.users.activate', $target))
            ->assertRedirect(route('root.backoffice.users.show', $target))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $target->getKey(),
            'status' => UserStatus::Active->value,
        ]);
    }

    public function test_root_can_deactivate_an_active_user(): void
    {
        $root = $this->actor('super_admin_root', ['users.view', 'users.deactivate']);
        $target = User::factory()->create([
            'display_name' => 'Compte à désactiver',
            'status' => UserStatus::Active,
        ]);

        $this->actingAs($root)
            ->patch(route('root.backoffice.users.deactivate', $target))
            ->assertRedirect(route('root.backoffice.users.show', $target))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $target->getKey(),
            'status' => UserStatus::Inactive->value,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_key' => 'identity.user.status_changed',
            'actor_id' => $root->getKey(),
            'subject_id' => $target->getKey(),
        ]);
    }

    public function test_root_can_suspend_an_active_user_from_the_detail_page(): void
    {
        $root = $this->actor('super_admin_root', ['users.view', 'users.suspend']);
        $target = User::factory()->create([
            'display_name' => 'Compte à suspendre',
            'status' => UserStatus::Active,
        ]);

        $this->actingAs($root)
            ->patch(route('root.backoffice.users.suspend', $target))
            ->assertRedirect(route('root.backoffice.users.show', $target))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $target->getKey(),
            'status' => UserStatus::Suspended->value,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_key' => 'identity.user.status_changed',
            'actor_id' => $root->getKey(),
            'subject_id' => $target->getKey(),
        ]);
    }

    public function test_assigned_super_admin_can_suspend_user_in_its_cooperative(): void
    {
        $cooperative = Cooperative::factory()->create();
        $actor = $this->actor('super_admin', ['users.suspend']);
        $actor->cooperatives()->attach($cooperative->getKey());
        $target = User::factory()->create([
            'cooperative_id' => $cooperative->getKey(),
            'status' => UserStatus::Active,
        ]);

        $this->actingAs($actor)
            ->patch(route('backoffice.users.suspend', $target))
            ->assertRedirect(route('backoffice.users.show', $target))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $target->getKey(),
            'status' => UserStatus::Suspended->value,
        ]);
    }

    public function test_super_admin_cannot_suspend_user_outside_assigned_cooperatives(): void
    {
        $assigned = Cooperative::factory()->create();
        $unassigned = Cooperative::factory()->create();
        $actor = $this->actor('super_admin', ['users.suspend']);
        $actor->cooperatives()->attach($assigned->getKey());
        $target = User::factory()->create([
            'cooperative_id' => $unassigned->getKey(),
            'status' => UserStatus::Active,
        ]);

        $this->actingAs($actor)
            ->patch(route('backoffice.users.suspend', $target))
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $target->getKey(),
            'status' => UserStatus::Active->value,
        ]);
    }

    public function test_user_suspension_requires_its_specific_permission(): void
    {
        $root = $this->actor('super_admin_root', ['users.view']);
        $target = User::factory()->create(['status' => UserStatus::Active]);

        $this->actingAs($root)
            ->patch(route('root.backoffice.users.suspend', $target))
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $target->getKey(),
            'status' => UserStatus::Active->value,
        ]);
    }

    public function test_already_inactive_user_cannot_be_suspended(): void
    {
        $root = $this->actor('super_admin_root', ['users.suspend']);
        $target = User::factory()->create(['status' => UserStatus::Inactive]);

        $this->actingAs($root)
            ->patch(route('root.backoffice.users.suspend', $target))
            ->assertForbidden();
    }

    public function test_user_activation_requires_its_specific_permission(): void
    {
        $root = $this->actor('super_admin_root', ['users.view']);
        $target = User::factory()->create(['status' => UserStatus::Pending]);

        $this->actingAs($root)
            ->patch(route('root.backoffice.users.activate', $target))
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $target->getKey(),
            'status' => UserStatus::Pending->value,
        ]);
    }

    public function test_user_deactivation_requires_its_specific_permission(): void
    {
        $root = $this->actor('super_admin_root', ['users.view']);
        $target = User::factory()->create(['status' => UserStatus::Active]);

        $this->actingAs($root)
            ->patch(route('root.backoffice.users.deactivate', $target))
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $target->getKey(),
            'status' => UserStatus::Active->value,
        ]);
    }

    public function test_root_cannot_activate_an_already_active_user(): void
    {
        $root = $this->actor('super_admin_root', ['users.view', 'users.activate']);
        $target = User::factory()->create(['status' => UserStatus::Active]);

        $this->actingAs($root)
            ->patch(route('root.backoffice.users.activate', $target))
            ->assertForbidden();
    }

    public function test_root_can_archive_an_active_user_from_the_detail_page(): void
    {
        $root = $this->actor('super_admin_root', ['users.view', 'users.archive']);
        $target = User::factory()->create([
            'display_name' => 'Compte à archiver',
            'status' => UserStatus::Active,
        ]);

        $this->actingAs($root)
            ->patch(route('root.backoffice.users.archive', $target))
            ->assertRedirect(route('root.backoffice.users.show', $target))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $target->getKey(),
            'status' => UserStatus::Archived->value,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_key' => 'identity.user.status_changed',
            'actor_id' => $root->getKey(),
            'subject_id' => $target->getKey(),
        ]);
    }

    public function test_root_can_restore_an_archived_user_to_pending(): void
    {
        $root = $this->actor('super_admin_root', ['users.view', 'users.restore']);
        $target = User::factory()->create([
            'display_name' => 'Compte archivé',
            'status' => UserStatus::Archived,
        ]);

        $this->actingAs($root)
            ->patch(route('root.backoffice.users.restore', $target))
            ->assertRedirect(route('root.backoffice.users.show', $target))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $target->getKey(),
            'status' => UserStatus::Pending->value,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_key' => 'identity.user.status_changed',
            'actor_id' => $root->getKey(),
            'subject_id' => $target->getKey(),
        ]);
    }

    public function test_archiving_requires_its_specific_permission(): void
    {
        $root = $this->actor('super_admin_root', ['users.view']);
        $target = User::factory()->create(['status' => UserStatus::Active]);

        $this->actingAs($root)
            ->patch(route('root.backoffice.users.archive', $target))
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $target->getKey(),
            'status' => UserStatus::Active->value,
        ]);
    }

    public function test_restoring_archived_user_requires_its_specific_permission(): void
    {
        $root = $this->actor('super_admin_root', ['users.view']);
        $target = User::factory()->create(['status' => UserStatus::Archived]);

        $this->actingAs($root)
            ->patch(route('root.backoffice.users.restore', $target))
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $target->getKey(),
            'status' => UserStatus::Archived->value,
        ]);
    }

    public function test_non_root_user_cannot_archive_or_restore_users(): void
    {
        $actor = $this->actor('super_admin', ['users.archive', 'users.restore']);
        $active = User::factory()->create(['status' => UserStatus::Active]);
        $archived = User::factory()->create(['status' => UserStatus::Archived]);

        $this->actingAs($actor)
            ->patch(route('backoffice.users.archive', $active))
            ->assertForbidden();

        $this->patch(route('root.backoffice.users.restore', $archived))
            ->assertForbidden();
    }

    public function test_archived_user_cannot_be_archived_again(): void
    {
        $root = $this->actor('super_admin_root', ['users.view', 'users.archive']);
        $target = User::factory()->create(['status' => UserStatus::Archived]);

        $this->actingAs($root)
            ->patch(route('root.backoffice.users.archive', $target))
            ->assertForbidden();
    }

    public function test_non_archived_user_cannot_be_restored(): void
    {
        $root = $this->actor('super_admin_root', ['users.view', 'users.restore']);
        $target = User::factory()->create(['status' => UserStatus::Active]);

        $this->actingAs($root)
            ->patch(route('backoffice.users.restore', $target))
            ->assertForbidden();
    }

    public function test_super_admin_list_is_limited_to_assigned_cooperatives(): void
    {
        $assigned = Cooperative::factory()->create();
        $unassigned = Cooperative::factory()->create();
        $actor = $this->actor('super_admin', ['users.view']);
        $actor->cooperatives()->attach($assigned->getKey());
        User::factory()->create([
            'display_name' => 'Utilisateur autorisé',
            'cooperative_id' => $assigned->getKey(),
        ]);
        User::factory()->create([
            'display_name' => 'Utilisateur hors périmètre',
            'cooperative_id' => $unassigned->getKey(),
        ]);

        $this->actingAs($actor)
            ->get(route('backoffice.users.index'))
            ->assertOk()
            ->assertSee('Utilisateur autorisé')
            ->assertDontSee('Utilisateur hors périmètre');
    }

    public function test_cooperative_admin_list_is_limited_to_own_cooperative(): void
    {
        $own = Cooperative::factory()->create();
        $other = Cooperative::factory()->create();
        $actor = $this->actor('cooperative_admin', ['users.view'], [
            'cooperative_id' => $own->getKey(),
        ]);
        User::factory()->create([
            'display_name' => 'Utilisateur de ma coopérative',
            'cooperative_id' => $own->getKey(),
        ]);
        User::factory()->create([
            'display_name' => 'Utilisateur autre coopérative',
            'cooperative_id' => $other->getKey(),
        ]);

        $this->actingAs($actor)
            ->get(route('backoffice.users.index'))
            ->assertOk()
            ->assertSee('Utilisateur de ma coopérative')
            ->assertDontSee('Utilisateur autre coopérative');
    }

    public function test_non_root_user_cannot_request_deleted_users(): void
    {
        $cooperative = Cooperative::factory()->create();
        $actor = $this->actor('super_admin', ['users.view']);
        $actor->cooperatives()->attach($cooperative->getKey());

        $this->actingAs($actor)
            ->get(route('backoffice.users.index', ['deleted' => 1]))
            ->assertForbidden();
    }

    public function test_user_without_users_view_permission_is_forbidden(): void
    {
        $root = $this->actor('super_admin_root', []);

        $this->actingAs($root)
            ->get(route('root.backoffice.users.index'))
            ->assertForbidden();
    }

    public function test_root_can_soft_delete_user_from_detail_page(): void
    {
        $root = $this->actor('super_admin_root', ['users.view', 'users.delete']);
        $target = User::factory()->create(['display_name' => 'Compte à supprimer']);

        $this->actingAs($root)
            ->delete(route('root.backoffice.users.destroy', $target))
            ->assertRedirect(route('root.backoffice.users.index'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('users', ['id' => $target->getKey()]);
        $this->assertDatabaseHas('audit_events', [
            'event_key' => 'identity.user.deleted',
            'actor_id' => $root->getKey(),
            'subject_id' => $target->getKey(),
        ]);
    }

    public function test_user_deletion_requires_its_specific_permission(): void
    {
        $root = $this->actor('super_admin_root', ['users.view']);
        $target = User::factory()->create();

        $this->actingAs($root)
            ->delete(route('root.backoffice.users.destroy', $target))
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $target->getKey(),
            'deleted_at' => null,
        ]);
    }

    public function test_non_root_user_cannot_soft_delete_or_restore_deleted_users(): void
    {
        $actor = $this->actor('super_admin', ['users.delete', 'users.restore_deleted']);
        $active = User::factory()->create();
        $deleted = User::factory()->create();
        $deleted->delete();

        $this->actingAs($actor)
            ->delete(route('backoffice.users.destroy', $active))
            ->assertForbidden();

        $this->patch(route('root.backoffice.users.restore-deleted', $deleted->getKey()))
            ->assertForbidden();
    }

    public function test_root_can_list_and_restore_soft_deleted_users(): void
    {
        $root = $this->actor('super_admin_root', ['users.view', 'users.restore_deleted']);
        $target = User::factory()->create([
            'display_name' => 'Compte supprimé à restaurer',
            'status' => UserStatus::Inactive,
        ]);
        $target->delete();

        $this->actingAs($root)
            ->get(route('root.backoffice.users.index', ['deleted' => 1]))
            ->assertOk()
            ->assertSee('Compte supprimé à restaurer')
            ->assertSee('Comptes supprimés logiquement')
            ->assertSee(route('root.backoffice.users.restore-deleted', $target->getKey()));

        $this->patch(route('root.backoffice.users.restore-deleted', $target->getKey()))
            ->assertRedirect(route('root.backoffice.users.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $target->getKey(),
            'deleted_at' => null,
            'status' => UserStatus::Pending->value,
            'email_verified_at' => null,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_key' => 'identity.user.restored',
            'actor_id' => $root->getKey(),
            'subject_id' => $target->getKey(),
        ]);
    }

    public function test_restoring_deleted_user_requires_its_specific_permission(): void
    {
        $root = $this->actor('super_admin_root', ['users.view']);
        $target = User::factory()->create();
        $target->delete();

        $this->actingAs($root)
            ->patch(route('root.backoffice.users.restore-deleted', $target->getKey()))
            ->assertForbidden();

        $this->assertSoftDeleted('users', ['id' => $target->getKey()]);
    }

    public function test_root_can_access_user_creation_form_with_all_allowed_roles(): void
    {
        $root = $this->actor('super_admin_root', ['users.create']);

        Role::query()->firstOrCreate(
            ['slug' => 'cooperative_admin'],
            ['name' => 'Cooperative Admin', 'is_active' => true],
        );

        Role::query()->firstOrCreate(
            ['slug' => 'user'],
            ['name' => 'User', 'is_active' => true],
        );

        Role::query()->firstOrCreate(
            ['slug' => 'super_admin'],
            ['name' => 'Super Admin', 'is_active' => true],
        );

        $this->actingAs($root)
            ->get(route('root.backoffice.users.create'))
            ->assertOk()
            ->assertViewIs('backoffice.users.create')
            ->assertSee('value="super_admin"', false)
            ->assertSee('value="cooperative_admin"', false)
            ->assertSee('value="user"', false);
    }

    public function test_assigned_super_admin_can_access_user_creation_form_with_limited_roles_and_cooperatives(): void
    {
        $cooperative = Cooperative::factory()->create();

        $actor = $this->actor('super_admin', ['users.create']);
        $actor->cooperatives()->attach($cooperative->getKey());

        Role::query()->firstOrCreate(
            ['slug' => 'cooperative_admin'],
            ['name' => 'Cooperative Admin', 'is_active' => true],
        );

        Role::query()->firstOrCreate(
            ['slug' => 'user'],
            ['name' => 'User', 'is_active' => true],
        );

        Role::query()->firstOrCreate(
            ['slug' => 'super_admin'],
            ['name' => 'Super Admin', 'is_active' => true],
        );

        $this->actingAs($actor)
            ->get(route('backoffice.users.create'))
            ->assertOk()
            ->assertViewIs('backoffice.users.create')
            ->assertDontSee('value="super_admin"', false)
            ->assertSee('value="cooperative_admin"', false)
            ->assertSee('value="user"', false)
            ->assertSee($cooperative->name);
    }

    public function test_super_admin_without_cooperative_assignment_cannot_access_user_creation_form(): void
    {
        $actor = $this->actor('super_admin', ['users.create']);

        $this->actingAs($actor)
            ->get(route('backoffice.users.create'))
            ->assertForbidden();
    }

    public function test_cooperative_admin_cannot_access_user_creation_form(): void
    {
        $cooperative = Cooperative::factory()->create();

        $actor = $this->actor('cooperative_admin', ['users.create'], [
            'cooperative_id' => $cooperative->getKey(),
        ]);

        $this->actingAs($actor)
            ->get(route('backoffice.users.create'))
            ->assertForbidden();
    }

    public function test_regular_user_cannot_access_user_creation_form(): void
    {
        $actor = $this->actor('user', ['users.create']);

        $this->actingAs($actor)
            ->get(route('root.backoffice.users.create'))
            ->assertForbidden();
    }


    public function test_root_can_create_super_admin_without_cooperative_assignment_and_send_invitation(): void
    {
        Notification::fake();

        $root = $this->actor('super_admin_root', ['users.create']);
        Role::query()->firstOrCreate(
            ['slug' => 'super_admin'],
            ['name' => 'Super Admin', 'is_active' => true],
        );

        $payload = $this->validCreationPayload('super_admin');

        $this->actingAs($root)
            ->post(route('root.backoffice.users.store'), $payload)
            ->assertRedirect(route('root.backoffice.users.index'))
            ->assertSessionHas('success');

        $created = User::query()->where('email', $payload['email'])->firstOrFail();

        $this->assertSame(UserStatus::Pending, $created->status);
        $this->assertNull($created->cooperative_id);
        $this->assertNull($created->email_verified_at);
        $this->assertNotSame('password', $created->password);
        $this->assertDatabaseHas('user_role', [
            'user_id' => $created->getKey(),
            'role_id' => Role::query()->where('slug', 'super_admin')->value('id'),
        ]);
        $this->assertDatabaseHas('audit_events', [
            'event_key' => 'identity.user.created',
            'actor_id' => $root->getKey(),
            'subject_id' => $created->getKey(),
        ]);

        $invitation = UserInvitation::query()
            ->where('user_id', $created->getKey())
            ->firstOrFail();

        $this->assertSame(64, strlen($invitation->token_hash));
        $this->assertNull($invitation->used_at);
        $this->assertNull($invitation->revoked_at);
        $this->assertTrue($invitation->expires_at->between(
            now()->addMinutes(59),
            now()->addMinutes(61),
        ));
        Notification::assertSentOnDemand(UserInvitationNotification::class);
    }

    public function test_root_cannot_create_a_user_directly_as_active(): void
    {
        Notification::fake();

        $root = $this->actor('super_admin_root', ['users.create']);
        $cooperative = Cooperative::factory()->create();

        Role::query()->firstOrCreate(
            ['slug' => 'user'],
            ['name' => 'User', 'is_active' => true],
        );

        $payload = $this->validCreationPayload(
            'user',
            $cooperative->getKey(),
            UserStatus::Active,
        );

        $this->actingAs($root)
            ->post(route('root.backoffice.users.store'), $payload)
            ->assertSessionHasErrors('status');

        $this->assertDatabaseMissing('users', ['email' => $payload['email']]);
        $this->assertDatabaseCount('user_invitations', 0);
        Notification::assertNothingSent();
    }

    public function test_root_can_create_user_in_pending_status_and_send_activation_invitation(): void
    {
        Notification::fake();

        $root = $this->actor('super_admin_root', ['users.create']);
        $cooperative = Cooperative::factory()->create();

        Role::query()->firstOrCreate(
            ['slug' => 'user'],
            ['name' => 'User', 'is_active' => true],
        );

        $payload = $this->validCreationPayload(
            'user',
            $cooperative->getKey(),
            UserStatus::Pending,
        );

        $this->actingAs($root)
            ->post(route('root.backoffice.users.store'), $payload)
            ->assertRedirect(route('root.backoffice.users.index'))
            ->assertSessionHas('success');

        $created = User::query()->where('email', $payload['email'])->firstOrFail();

        $this->assertSame(UserStatus::Pending, $created->status);
        $this->assertNull($created->email_verified_at);
        $this->assertDatabaseHas('user_invitations', [
            'user_id' => $created->getKey(),
            'purpose' => 'account_activation',
            'used_at' => null,
            'revoked_at' => null,
        ]);

        Notification::assertSentOnDemand(UserInvitationNotification::class);
    }

    public function test_root_can_create_cooperative_admin_and_user_in_selected_cooperative(): void
    {
        Notification::fake();

        $root = $this->actor('super_admin_root', ['users.create']);
        $cooperative = Cooperative::factory()->create();

        foreach (['cooperative_admin', 'user'] as $roleSlug) {
            Role::query()->firstOrCreate(
                ['slug' => $roleSlug],
                ['name' => str($roleSlug)->replace('_', ' ')->title()->toString(), 'is_active' => true],
            );

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

    public function test_assigned_super_admin_can_create_cooperative_admin_or_user_only_in_assigned_cooperative(): void
    {
        Notification::fake();

        $assigned = Cooperative::factory()->create();
        $actor = $this->actor('super_admin', ['users.create']);
        $actor->cooperatives()->attach($assigned->getKey());

        foreach (['cooperative_admin', 'user'] as $roleSlug) {
            Role::query()->firstOrCreate(
                ['slug' => $roleSlug],
                ['name' => str($roleSlug)->replace('_', ' ')->title()->toString(), 'is_active' => true],
            );

            $payload = $this->validCreationPayload($roleSlug, $assigned->getKey());

            $this->actingAs($actor)
                ->post(route('backoffice.users.store'), $payload)
                ->assertRedirect(route('backoffice.users.index'))
                ->assertSessionHas('success');

            $created = User::query()->where('email', $payload['email'])->firstOrFail();

            $this->assertSame(UserStatus::Pending, $created->status);
            $this->assertSame((string) $assigned->getKey(), (string) $created->cooperative_id);
            $this->assertTrue($created->roles()->where('slug', $roleSlug)->exists());
        }

        Notification::assertSentOnDemand(UserInvitationNotification::class);
    }

    public function test_super_admin_without_assignment_cannot_create_user(): void
    {
        Notification::fake();

        $actor = $this->actor('super_admin', ['users.create']);
        $cooperative = Cooperative::factory()->create();
        Role::query()->firstOrCreate(
            ['slug' => 'user'],
            ['name' => 'User', 'is_active' => true],
        );
        $payload = $this->validCreationPayload('user', $cooperative->getKey());

        $this->actingAs($actor)
            ->post(route('backoffice.users.store'), $payload)
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => $payload['email']]);
        $this->assertDatabaseCount('user_invitations', 0);
        Notification::assertNothingSent();
    }

    public function test_super_admin_cannot_create_user_in_unassigned_cooperative(): void
    {
        Notification::fake();

        $assigned = Cooperative::factory()->create();
        $unassigned = Cooperative::factory()->create();
        $actor = $this->actor('super_admin', ['users.create']);
        $actor->cooperatives()->attach($assigned->getKey());

        Role::query()->firstOrCreate(
            ['slug' => 'user'],
            ['name' => 'User', 'is_active' => true],
        );
        $payload = $this->validCreationPayload('user', $unassigned->getKey());

        $this->actingAs($actor)
            ->post(route('backoffice.users.store'), $payload)
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('users', ['email' => $payload['email']]);
        $this->assertDatabaseCount('user_invitations', 0);
        Notification::assertNothingSent();
    }

    public function test_cooperative_admin_and_regular_user_cannot_create_users(): void
    {
        Notification::fake();

        $cooperative = Cooperative::factory()->create();
        Role::query()->firstOrCreate(
            ['slug' => 'user'],
            ['name' => 'User', 'is_active' => true],
        );

        foreach (['cooperative_admin', 'user'] as $roleSlug) {
            $actor = $this->actor($roleSlug, ['users.create'], [
                'cooperative_id' => $cooperative->getKey(),
            ]);
            $payload = $this->validCreationPayload(
                'user',
                $cooperative->getKey(),
            );

            $this->actingAs($actor)
                ->post(route('backoffice.users.store'), $payload)
                ->assertForbidden();

            $this->assertDatabaseMissing('users', ['email' => $payload['email']]);
        }

        $this->assertDatabaseCount('user_invitations', 0);
        Notification::assertNothingSent();
    }


    public function test_failed_invitation_delivery_keeps_user_pending_and_revokes_invitation(): void
    {
        $root = $this->actor(
            'super_admin_root',
            ['users.create'],
        );

        $cooperative = Cooperative::factory()->create();

        Role::query()->firstOrCreate(
            ['slug' => 'user'],
            [
                'name' => 'User',
                'is_active' => true,
            ],
        );

        $payload = $this->validCreationPayload(
            'user',
            $cooperative->getKey(),
        );

        /*
        |--------------------------------------------------------------------------
        | Simulate a mail transport failure
        |--------------------------------------------------------------------------
        */

        Notification::extend('mail', function () {
            return new class {
                public function send(
                    object $notifiable,
                    object $notification,
                ): void {
                    throw new RuntimeException(
                        'Mail transport unavailable.',
                    );
                }
            };
        });

        /*
        |--------------------------------------------------------------------------
        | Execute HTTP request
        |--------------------------------------------------------------------------
        */

        $this->actingAs($root)
            ->post(
                route('root.backoffice.users.store'),
                $payload,
            )
            ->assertRedirect()
            ->assertSessionHas('error')
            ->assertSessionMissing('success');

        /*
        |--------------------------------------------------------------------------
        | Verify user remains pending
        |--------------------------------------------------------------------------
        */

        $created = User::query()
            ->where('email', $payload['email'])
            ->firstOrFail();

        $this->assertSame(
            UserStatus::Pending,
            $created->status,
        );

        /*
        |--------------------------------------------------------------------------
        | Verify invitation is revoked
        |--------------------------------------------------------------------------
        */

        $invitation = UserInvitation::query()
            ->where('user_id', $created->getKey())
            ->firstOrFail();

        $this->assertNotNull($invitation->revoked_at);

        $this->assertNull($invitation->used_at);
    }

    public function test_root_can_resend_invitation_to_pending_user(): void
    {
        Notification::fake();

        $root = $this->actor('super_admin_root', ['users.create']);
        $target = User::factory()->create([
            'status' => UserStatus::Pending,
            'email_verified_at' => null,
        ]);

        $this->actingAs($root)
            ->post(route('root.backoffice.users.resend-invitation', $target))
            ->assertRedirect(route('root.backoffice.users.show', $target))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('user_invitations', [
            'user_id' => $target->getKey(),
            'used_at' => null,
            'revoked_at' => null,
        ]);

        Notification::assertSentOnDemand(UserInvitationNotification::class);

        $this->assertDatabaseHas('audit_events', [
            'event_key' => 'identity.user.invitation_sent',
            'subject_id' => $target->getKey(),
        ]);
    }

    public function test_assigned_super_admin_can_resend_invitation_in_its_cooperative(): void
    {
        Notification::fake();

        $cooperative = Cooperative::factory()->create();

        $actor = $this->actor('super_admin', ['users.create']);
        $actor->cooperatives()->attach($cooperative->getKey());

        $target = User::factory()->create([
            'cooperative_id' => $cooperative->getKey(),
            'status' => UserStatus::Pending,
            'email_verified_at' => null,
        ]);

        $this->actingAs($actor)
            ->post(route('backoffice.users.resend-invitation', $target))
            ->assertRedirect(route('backoffice.users.show', $target))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('user_invitations', [
            'user_id' => $target->getKey(),
            'used_at' => null,
            'revoked_at' => null,
        ]);

        Notification::assertSentOnDemand(UserInvitationNotification::class);
    }

    public function test_super_admin_cannot_resend_invitation_outside_assigned_cooperatives(): void
    {
        Notification::fake();

        $assigned = Cooperative::factory()->create();
        $unassigned = Cooperative::factory()->create();

        $actor = $this->actor('super_admin', ['users.create']);
        $actor->cooperatives()->attach($assigned->getKey());

        $target = User::factory()->create([
            'cooperative_id' => $unassigned->getKey(),
            'status' => UserStatus::Pending,
            'email_verified_at' => null,
        ]);

        $this->actingAs($actor)
            ->post(route('backoffice.users.resend-invitation', $target))
            ->assertForbidden();

        $this->assertDatabaseMissing('user_invitations', [
            'user_id' => $target->getKey(),
        ]);

        Notification::assertNothingSent();
    }

    public function test_cooperative_admin_and_regular_user_cannot_resend_invitations(): void
    {
        Notification::fake();

        $cooperative = Cooperative::factory()->create();

        $target = User::factory()->create([
            'cooperative_id' => $cooperative->getKey(),
            'status' => UserStatus::Pending,
            'email_verified_at' => null,
        ]);

        foreach (['cooperative_admin', 'user'] as $roleSlug) {
            $actor = $this->actor($roleSlug, ['users.create'], [
                'cooperative_id' => $cooperative->getKey(),
            ]);

            $this->actingAs($actor)
                ->post(route('root.backoffice.users.resend-invitation', $target))
                ->assertForbidden();
        }

        $this->assertDatabaseMissing('user_invitations', [
            'user_id' => $target->getKey(),
        ]);

        Notification::assertNothingSent();
    }

    public function test_invitation_cannot_be_resent_to_non_pending_user(): void
    {
        Notification::fake();

        $root = $this->actor('super_admin_root', ['users.create']);

        foreach ([
            UserStatus::Active,
            UserStatus::Inactive,
            UserStatus::Suspended,
            UserStatus::Archived,
        ] as $status) {
            $target = User::factory()->create([
                'status' => $status,
            ]);

            $this->actingAs($root)
                ->post(route('root.backoffice.users.resend-invitation', $target))
                ->assertForbidden();
        }

        $this->assertDatabaseCount('user_invitations', 0);

        Notification::assertNothingSent();
    }

    public function test_failed_resend_keeps_user_pending_and_revokes_new_invitation(): void
    {
        $root = $this->actor('super_admin_root', ['users.create']);

        $target = User::factory()->create([
            'status' => UserStatus::Pending,
            'email_verified_at' => null,
        ]);

        Notification::extend('mail', function () {
            return new class {
                public function send(
                    object $notifiable,
                    object $notification,
                ): void {
                    throw new RuntimeException('Mail transport unavailable.');
                }
            };
        });

        $this->actingAs($root)
            ->post(route('root.backoffice.users.resend-invitation', $target))
            ->assertRedirect(route('root.backoffice.users.show', $target))
            ->assertSessionHas('error')
            ->assertSessionMissing('success');

        $this->assertDatabaseHas('users', [
            'id' => $target->getKey(),
            'status' => UserStatus::Pending->value,
        ]);

        $invitation = UserInvitation::query()
            ->where('user_id', $target->getKey())
            ->firstOrFail();

        $this->assertNotNull($invitation->revoked_at);
        $this->assertNull($invitation->used_at);
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

    /** @param list<string> $permissions
     *  @param array<string, mixed> $attributes
     */

    public function test_root_uses_its_dedicated_user_management_space(): void
    {
        $root = $this->actor('super_admin_root', ['users.view']);

        $this->actingAs($root)
            ->get(route('root.backoffice.users.index'))
            ->assertOk()
            ->assertViewIs('super-admin-root.users.index');

        $this->actingAs($root)
            ->get(route('backoffice.users.index'))
            ->assertForbidden();
    }

    public function test_super_admin_cannot_access_root_user_management_space(): void
    {
        $superAdmin = $this->actor('super_admin', ['users.view']);

        $this->actingAs($superAdmin)
            ->get(route('root.backoffice.users.index'))
            ->assertForbidden();
    }

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
