<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Identity\Authentication\Http\Controllers;

use App\Domains\Identity\Authentication\Models\AuthenticationSession;
use App\Domains\Identity\Authorization\Models\Permission;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Identity\Authentication\Http\Middleware\EnsureActiveAuthenticationSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AuthenticationSessionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureActiveAuthenticationSession::class);
    }

    public function test_root_user_detail_displays_only_active_sessions_of_the_selected_user(): void
    {
        $root = $this->actor('super_admin_root', ['users.view']);
        $target = User::factory()->create();
        $otherUser = User::factory()->create();

        $targetSession = $this->session($target, 'target-device-session', 'Target laptop');
        $this->session($target, 'target-phone-session', 'Target phone');
        $this->session($target, 'target-revoked-session', 'Old revoked device', true);
        $this->session($otherUser, 'other-user-session', 'Private other device');

        $response = $this->actingAs($root)
            ->get(route('root.backoffice.users.show', $target));

        $response->assertOk()
            ->assertViewIs('super-admin-root.users.show')
            ->assertViewHas('sessions', fn ($sessions) => $sessions->count() === 2)
            ->assertSee('Target laptop')
            ->assertSee('Target phone')
            ->assertDontSee('Old revoked device')
            ->assertDontSee('Private other device');

        $this->assertNotNull($targetSession->fresh());
    }

    public function test_root_can_revoke_one_session_from_the_user_detail_without_revoking_other_sessions(): void
    {
        $root = $this->actor('super_admin_root', ['users.view']);
        $target = User::factory()->create();
        $selected = $this->session($target, 'selected-device-session', 'Selected device');
        $other = $this->session($target, 'preserved-device-session', 'Preserved device');

        $this->actingAs($root)
            ->delete(route('root.backoffice.users.sessions.destroy', [
                'user' => $target,
                'session' => $selected,
            ]))
            ->assertRedirect(route('root.backoffice.users.show', $target))
            ->assertSessionHas('success');

        $this->assertNotNull($selected->fresh()->revoked_at);
        $this->assertSame('root_revocation', $selected->fresh()->revocation_reason);
        $this->assertNull($other->fresh()->revoked_at);
    }

    public function test_root_cannot_revoke_a_session_through_another_users_detail_url(): void
    {
        $root = $this->actor('super_admin_root', ['users.view']);
        $target = User::factory()->create();
        $otherUser = User::factory()->create();
        $foreignSession = $this->session($otherUser, 'foreign-device-session', 'Foreign device');

        $this->actingAs($root)
            ->delete(route('root.backoffice.users.sessions.destroy', [
                'user' => $target,
                'session' => $foreignSession,
            ]))
            ->assertNotFound();

        $this->assertNull($foreignSession->fresh()->revoked_at);
    }

    public function test_user_can_see_only_own_active_devices_in_personal_security_area(): void
    {
        $user = $this->actor('user');
        $otherUser = User::factory()->create();
        $ownSession = $this->session($user, 'own-device-session', 'My personal laptop');
        $this->session($otherUser, 'foreign-device-session', 'Someone else device');

        $this->actingAs($user)
            ->get(route('backoffice.security.sessions.index'))
            ->assertOk()
            ->assertViewIs('identity.security.sessions.index')
            ->assertViewHas('sessions', fn ($sessions) => $sessions->count() === 1)
            ->assertSee('My personal laptop')
            ->assertDontSee('Someone else device');

        $this->assertNull($ownSession->fresh()->revoked_at);
    }

    public function test_user_can_revoke_only_one_of_own_devices(): void
    {
        $user = $this->actor('user');
        $selected = $this->session($user, 'my-selected-device', 'My selected device');
        $other = $this->session($user, 'my-other-device', 'My other device');

        $this->actingAs($user)
            ->delete(route('backoffice.security.sessions.destroy', $selected))
            ->assertRedirect(route('backoffice.security.sessions.index'))
            ->assertSessionHas('success');

        $this->assertNotNull($selected->fresh()->revoked_at);
        $this->assertSame('user_revocation', $selected->fresh()->revocation_reason);
        $this->assertNull($other->fresh()->revoked_at);
    }

    public function test_user_cannot_revoke_another_users_session_by_identifier(): void
    {
        $user = $this->actor('user');
        $otherUser = User::factory()->create();
        $foreignSession = $this->session($otherUser, 'foreign-session-id', 'Foreign device');

        $this->actingAs($user)
            ->delete(route('backoffice.security.sessions.destroy', $foreignSession))
            ->assertNotFound();

        $this->assertNull($foreignSession->fresh()->revoked_at);
    }

    public function test_root_has_a_separate_security_tab_for_its_own_devices(): void
    {
        $root = $this->actor('super_admin_root');
        $otherUser = User::factory()->create();
        $this->session($root, 'root-own-device', 'Root private workstation');
        $this->session($otherUser, 'other-private-device', 'Other private device');

        $this->actingAs($root)
            ->get(route('root.backoffice.security.sessions.index'))
            ->assertOk()
            ->assertViewIs('identity.security.sessions.index')
            ->assertViewHas('sessions', fn ($sessions) => $sessions->count() === 1)
            ->assertSee('Root private workstation')
            ->assertDontSee('Other private device');
    }

    public function test_non_root_user_cannot_access_root_security_tab_or_root_user_session_management(): void
    {
        $user = $this->actor('user');
        $target = User::factory()->create();
        $session = $this->session($target, 'target-device', 'Target device');

        $this->actingAs($user)
            ->get(route('root.backoffice.security.sessions.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('root.backoffice.users.sessions.destroy', [
                'user' => $target,
                'session' => $session,
            ]))
            ->assertForbidden();

        $this->assertNull($session->fresh()->revoked_at);
    }

    private function session(
        User $user,
        string $sessionId,
        string $device,
        bool $revoked = false,
    ): AuthenticationSession {
        return AuthenticationSession::query()->create([
            'user_id' => $user->getKey(),
            'session_id' => $sessionId,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'MAHLINE test browser',
            'browser' => 'Firefox',
            'device' => $device,
            'authenticated_at' => now(),
            'last_activity_at' => now(),
            'revoked_at' => $revoked ? now() : null,
            'revocation_reason' => $revoked ? 'logout' : null,
        ]);
    }

    /**
     * @param list<string> $permissions
     */
    private function actor(string $roleSlug, array $permissions = []): User
    {
        $actor = User::factory()->create([
            'status' => UserStatus::Active,
        ]);

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
