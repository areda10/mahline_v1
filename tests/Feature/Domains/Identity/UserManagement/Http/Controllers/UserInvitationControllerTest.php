<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Identity\UserManagement\Http\Controllers;

use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\UserManagement\Models\UserInvitation;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

final class UserInvitationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_invitation_displays_acceptance_form(): void
    {
        $token = bin2hex(random_bytes(32));
        $user = User::factory()->create([
            'status' => UserStatus::Pending,
            'email_verified_at' => null,
        ]);

        $this->createInvitation($user, $token);

        $this->get(route('user-invitations.show', [
            'token' => $token,
        ]))
            ->assertOk()
            ->assertViewIs('auth.accept-invitation')
            ->assertViewHas('token', $token)
            ->assertSee('Activer mon compte');
    }

    public function test_invalid_invitation_token_redirects_to_login(): void
    {
        $this->get(route('user-invitations.show', [
            'token' => 'invalid-token',
        ]))
            ->assertRedirect(route('authentication.login'))
            ->assertSessionHas('error');
    }

    public function test_expired_invitation_cannot_display_acceptance_form(): void
    {
        $token = bin2hex(random_bytes(32));
        $user = User::factory()->create([
            'status' => UserStatus::Pending,
            'email_verified_at' => null,
        ]);

        $this->createInvitation($user, $token, [
            'expires_at' => now()->subMinute(),
        ]);

        $this->get(route('user-invitations.show', [
            'token' => $token,
        ]))
            ->assertRedirect(route('authentication.login'))
            ->assertSessionHas('error');
    }

    public function test_revoked_invitation_cannot_display_acceptance_form(): void
    {
        $token = bin2hex(random_bytes(32));
        $user = User::factory()->create([
            'status' => UserStatus::Pending,
            'email_verified_at' => null,
        ]);

        $this->createInvitation($user, $token, [
            'revoked_at' => now(),
        ]);

        $this->get(route('user-invitations.show', [
            'token' => $token,
        ]))
            ->assertRedirect(route('authentication.login'))
            ->assertSessionHas('error');
    }

    public function test_used_invitation_cannot_display_acceptance_form(): void
    {
        $token = bin2hex(random_bytes(32));
        $user = User::factory()->create([
            'status' => UserStatus::Pending,
            'email_verified_at' => null,
        ]);

        $this->createInvitation($user, $token, [
            'used_at' => now(),
        ]);

        $this->get(route('user-invitations.show', [
            'token' => $token,
        ]))
            ->assertRedirect(route('authentication.login'))
            ->assertSessionHas('error');
    }

    public function test_inactive_user_cannot_display_acceptance_form(): void
    {
        $token = bin2hex(random_bytes(32));
        $user = User::factory()->create([
            'status' => UserStatus::Inactive,
            'email_verified_at' => null,
        ]);

        $this->createInvitation($user, $token);

        $this->get(route('user-invitations.show', [
            'token' => $token,
        ]))
            ->assertRedirect(route('authentication.login'))
            ->assertSessionHas('error');
    }

    public function test_valid_invitation_activates_user_and_consumes_token(): void
    {
        $token = bin2hex(random_bytes(32));

        $user = User::factory()->create([
            'status' => UserStatus::Pending,
            'email_verified_at' => null,
        ]);

        $invitation = $this->createInvitation($user, $token);

        $password = 'MahlineSecure2026';

        $this->post(route('user-invitations.accept'), [
            'token' => $token,
            'password' => $password,
            'password_confirmation' => $password,
        ])
            ->assertRedirect(route('authentication.login'))
            ->assertSessionHas('status');

        $user->refresh();
        $invitation->refresh();

        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check($password, $user->password));

        $this->assertNotNull($invitation->used_at);
        $this->assertNull($invitation->revoked_at);

        $this->assertDatabaseHas('audit_events', [
            'event_key' => 'identity.user.invitation_accepted',
            'subject_id' => $user->getKey(),
        ]);
    }

    public function test_root_manual_verification_invitation_only_verifies_active_user(): void
    {
        $token = bin2hex(random_bytes(32));
        $user = User::factory()->create([
            'status' => UserStatus::Active,
            'email_verified_at' => null,
        ]);

        $invitation = $this->createInvitation($user, $token, [
            'purpose' => 'root_manual_verification',
        ]);

        $this->get(route('user-invitations.show', ['token' => $token]))
            ->assertOk()
            ->assertViewIs('auth.accept-invitation');

        $password = 'MahlineSecure2026';
        $this->post(route('user-invitations.accept'), [
            'token' => $token,
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertRedirect(route('authentication.login'));

        $user->refresh();
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertFalse($user->must_change_password);
        $this->assertNotNull($invitation->fresh()->used_at);
    }

    public function test_password_confirmation_mismatch_does_not_activate_user(): void
    {
        $token = bin2hex(random_bytes(32));

        $user = User::factory()->create([
            'status' => UserStatus::Pending,
            'email_verified_at' => null,
        ]);

        $invitation = $this->createInvitation($user, $token);

        $this->post(route('user-invitations.accept'), [
            'token' => $token,
            'password' => 'MahlineSecure2026',
            'password_confirmation' => 'DifferentSecure2026',
        ])
            ->assertSessionHasErrors('password');

        $user->refresh();
        $invitation->refresh();

        $this->assertSame(UserStatus::Pending, $user->status);
        $this->assertNull($user->email_verified_at);
        $this->assertNull($invitation->used_at);
    }

    public function test_used_invitation_cannot_be_accepted_again(): void
    {
        $token = bin2hex(random_bytes(32));

        $user = User::factory()->create([
            'status' => UserStatus::Pending,
            'email_verified_at' => null,
        ]);

        $this->createInvitation($user, $token, [
            'used_at' => now(),
        ]);

        $this->post(route('user-invitations.accept'), [
            'token' => $token,
            'password' => 'MahlineSecure2026',
            'password_confirmation' => 'MahlineSecure2026',
        ])
            ->assertSessionHasErrors('invitation');

        $user->refresh();

        $this->assertSame(UserStatus::Pending, $user->status);
    }

    public function test_expired_invitation_cannot_be_accepted(): void
    {
        $token = bin2hex(random_bytes(32));

        $user = User::factory()->create([
            'status' => UserStatus::Pending,
            'email_verified_at' => null,
        ]);

        $this->createInvitation($user, $token, [
            'expires_at' => now()->subMinute(),
        ]);

        $this->post(route('user-invitations.accept'), [
            'token' => $token,
            'password' => 'MahlineSecure2026',
            'password_confirmation' => 'MahlineSecure2026',
        ])
            ->assertSessionHasErrors('invitation');

        $this->assertSame(
            UserStatus::Pending,
            $user->fresh()->status,
        );
    }

    public function test_revoked_invitation_cannot_be_accepted(): void
    {
        $token = bin2hex(random_bytes(32));

        $user = User::factory()->create([
            'status' => UserStatus::Pending,
            'email_verified_at' => null,
        ]);

        $this->createInvitation($user, $token, [
            'revoked_at' => now(),
        ]);

        $this->post(route('user-invitations.accept'), [
            'token' => $token,
            'password' => 'MahlineSecure2026',
            'password_confirmation' => 'MahlineSecure2026',
        ])
            ->assertSessionHasErrors('invitation');

        $this->assertSame(
            UserStatus::Pending,
            $user->fresh()->status,
        );
    }

    public function test_only_pending_users_can_accept_an_activation_invitation(): void
    {
        foreach ([
            UserStatus::Active,
            UserStatus::Inactive,
            UserStatus::Suspended,
            UserStatus::Archived,
        ] as $status) {
            $token = bin2hex(random_bytes(32));

            $user = User::factory()->create([
                'status' => $status,
                'email_verified_at' => null,
            ]);

            $invitation = $this->createInvitation($user, $token);

            $this->get(route('user-invitations.show', ['token' => $token]))
                ->assertRedirect(route('authentication.login'))
                ->assertSessionHas('error');

            $this->post(route('user-invitations.accept'), [
                'token' => $token,
                'password' => 'MahlineSecure2026',
                'password_confirmation' => 'MahlineSecure2026',
            ])->assertSessionHasErrors('invitation');

            $this->assertSame($status, $user->fresh()->status);
            $this->assertNull($user->fresh()->email_verified_at);
            $this->assertNull($invitation->fresh()->used_at);
        }
    }

    public function test_password_not_compliant_with_policy_is_rejected(): void
    {
        $token = bin2hex(random_bytes(32));

        $user = User::factory()->create([
            'status' => UserStatus::Pending,
            'email_verified_at' => null,
        ]);

        $invitation = $this->createInvitation($user, $token);

        $this->post(route('user-invitations.accept'), [
            'token' => $token,
            'password' => 'weakpassword',
            'password_confirmation' => 'weakpassword',
        ])
            ->assertSessionHasErrors('password');

        $this->assertSame(
            UserStatus::Pending,
            $user->fresh()->status,
        );

        $this->assertNull($invitation->fresh()->used_at);
    }

    public function test_invitation_token_cannot_be_reused_after_successful_acceptance(): void
    {
        $token = bin2hex(random_bytes(32));

        $user = User::factory()->create([
            'status' => UserStatus::Pending,
            'email_verified_at' => null,
        ]);

        $invitation = $this->createInvitation($user, $token);

        $payload = [
            'token' => $token,
            'password' => 'MahlineSecure2026',
            'password_confirmation' => 'MahlineSecure2026',
        ];

        $this->post(route('user-invitations.accept'), $payload)
            ->assertRedirect(route('authentication.login'))
            ->assertSessionHas('status');

        $this->post(route('user-invitations.accept'), $payload)
            ->assertSessionHasErrors('invitation');

        $this->assertSame(
            UserStatus::Active,
            $user->fresh()->status,
        );

        $this->assertNotNull($invitation->fresh()->used_at);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createInvitation(
        User $user,
        string $token,
        array $attributes = [],
    ): UserInvitation {
        return UserInvitation::query()->create([
            'id' => (string) Str::ulid(),
            'user_id' => $user->getKey(),
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addHour(),
            'used_at' => null,
            'revoked_at' => null,
            'created_at' => now(),
            ...$attributes,
        ]);
    }
}