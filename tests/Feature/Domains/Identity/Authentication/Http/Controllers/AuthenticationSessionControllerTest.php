<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Identity\Authentication\Http\Controllers;

use App\Domains\Identity\Authentication\Http\Middleware\EnsureActiveAuthenticationSession;
use App\Domains\Identity\Authentication\Models\AuthenticationSession;
use App\Domains\Identity\Authorization\Models\Permission;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
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

        $targetSession = $this->createAuthenticationSession($target, 'target-device-session', 'Target laptop');
        $this->createAuthenticationSession($target, 'target-phone-session', 'Target phone');
        $this->createAuthenticationSession($target, 'target-revoked-session', 'Old revoked device', true);
        $this->createAuthenticationSession($otherUser, 'other-user-session', 'Private other device');

        $response = $this->actingAs($root)
            ->get(route('root.backoffice.users.show', $target));

        $response->assertOk()
            ->assertViewIs('super-admin-root.users.show')
            ->assertViewHas('sessions', fn ($sessions) => $sessions instanceof Collection && $sessions->count() === 2)
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
        $selected = $this->createAuthenticationSession($target, 'selected-device-session', 'Selected device');
        $other = $this->createAuthenticationSession($target, 'preserved-device-session', 'Preserved device');

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
        $foreignSession = $this->createAuthenticationSession($otherUser, 'foreign-device-session', 'Foreign device');

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
        $ownSession = $this->createAuthenticationSession($user, 'own-device-session', 'My personal laptop');
        $this->createAuthenticationSession($otherUser, 'foreign-device-session', 'Someone else device');

        $this->actingAs($user)
            ->get(route('backoffice.security.sessions.index'))
            ->assertOk()
            ->assertViewIs('identity.security.sessions.index')
            ->assertViewHas('sessions', fn ($sessions) => $sessions instanceof Collection && $sessions->count() === 1)
            ->assertSee('My personal laptop')
            ->assertDontSee('Someone else device');

        $this->assertNull($ownSession->fresh()->revoked_at);
    }

    public function test_user_can_revoke_only_one_of_own_devices(): void
    {
        $user = $this->actor('user');
        $selected = $this->createAuthenticationSession($user, 'my-selected-device', 'My selected device');
        $other = $this->createAuthenticationSession($user, 'my-other-device', 'My other device');

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
        $foreignSession = $this->createAuthenticationSession($otherUser, 'foreign-session-id', 'Foreign device');

        $this->actingAs($user)
            ->delete(route('backoffice.security.sessions.destroy', $foreignSession))
            ->assertNotFound();

        $this->assertNull($foreignSession->fresh()->revoked_at);
    }

    public function test_root_has_a_separate_security_tab_for_its_own_devices(): void
    {
        $root = $this->actor('super_admin_root');
        $otherUser = User::factory()->create();
        $this->createAuthenticationSession($root, 'root-own-device', 'Root private workstation');
        $this->createAuthenticationSession($otherUser, 'other-private-device', 'Other private device');

        $this->actingAs($root)
            ->get(route('root.backoffice.security.sessions.index'))
            ->assertOk()
            ->assertViewIs('identity.security.sessions.index')
            ->assertViewHas('sessions', fn ($sessions) => $sessions instanceof Collection && $sessions->count() === 1)
            ->assertSee('Root private workstation')
            ->assertDontSee('Other private device');
    }

    public function test_non_root_user_cannot_access_root_security_tab_or_root_user_session_management(): void
    {
        $user = $this->actor('user');
        $target = User::factory()->create();
        $session = $this->createAuthenticationSession($target, 'target-device', 'Target device');

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

    public function test_user_can_extend_only_their_current_unexpired_session(): void
    {
        $this->travelTo(now()->startOfSecond());

        $user = $this->actor('user');
        $sessionId = Str::random(40);
        $session = $this->createAuthenticationSession($user, $sessionId, 'Current browser');
        $previousActivity = now()->subMinutes(59);

        $session->forceFill([
            'last_activity_at' => $previousActivity,
        ])->save();

        $this->withCurrentAuthenticationSession($user, $session)
            ->postJson(route('backoffice.security.sessions.extend'))
            ->assertOk()
            ->assertJson([
                'status' => 'extended',
                'timeout_seconds' => 3600,
            ]);

        $this->assertTrue($session->fresh()->last_activity_at->equalTo(now()));
        $this->assertNull($session->fresh()->revoked_at);
    }

    public function test_authenticated_layout_displays_the_two_minute_session_warning(): void
    {
        $user = $this->actor('user');
        $session = $this->createAuthenticationSession(
            $user,
            Str::random(40),
            'Current browser',
        );

        $this->withCurrentAuthenticationSession($user, $session)
            ->get(route('backoffice.security.sessions.index'))
            ->assertOk()
            ->assertSee('mahline-session-warning')
            ->assertSee('data-timeout-seconds="3600"', false)
            ->assertSee('data-warning-seconds="120"', false)
            ->assertSee(route('backoffice.security.sessions.extend'), false);
    }

    public function test_root_layout_uses_the_root_timeout_and_extension_route(): void
    {
        $root = $this->actor('super_admin_root');
        $session = $this->createAuthenticationSession(
            $root,
            session()->getId(),
            'Root browser',
        );

        $this->withCurrentAuthenticationSession($root, $session)
            ->get(route('root.backoffice.security.sessions.index'))
            ->assertOk()
            ->assertSee('mahline-session-warning')
            ->assertSee('data-timeout-seconds="900"', false)
            ->assertSee(route('root.backoffice.security.sessions.extend'), false);
    }

    public function test_expired_session_cannot_be_extended_or_revived(): void
    {
        $this->travelTo(now()->startOfSecond());

        $user = $this->actor('user');
        $session = $this->createAuthenticationSession(
            $user,
            session()->getId(),
            'Expired browser',
        );

        $session->forceFill([
            'last_activity_at' => now()->subMinutes(60),
        ])->save();

        $this->actingAs($user)
            ->withSession([
                'authentication_session_id' => (string) $session->getKey(),
            ])
            ->postJson(route('backoffice.security.sessions.extend'))
            ->assertUnauthorized()
            ->assertJson([
                'message' => 'La session a expiré. Veuillez vous reconnecter.',
            ]);

        $this->assertSame('inactivity_timeout', $session->fresh()->revocation_reason);
        $this->assertNotNull($session->fresh()->revoked_at);
    }

    public function test_root_can_extend_its_own_session_using_the_root_route(): void
    {
        $this->travelTo(now()->startOfSecond());

        $root = $this->actor('super_admin_root');
        $session = $this->createAuthenticationSession(
            $root,
            session()->getId(),
            'Root browser',
        );

        $this->withCurrentAuthenticationSession($root, $session)
            ->postJson(route('root.backoffice.security.sessions.extend'))
            ->assertOk()
            ->assertJson([
                'status' => 'extended',
                'timeout_seconds' => 900,
            ]);

        $this->assertNull($session->fresh()->revoked_at);
    }

    private function withCurrentAuthenticationSession(
        User $user,
        AuthenticationSession $session,
    ): static {
        return $this->actingAs($user)
            ->withCookie(
                config('session.cookie'),
                encrypt($session->session_id),
            )
            ->withSession([
                'authentication_session_id' => (string) $session->getKey(),
            ]);
    }

    private function createAuthenticationSession(
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
     * @param  list<string>  $permissions
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
