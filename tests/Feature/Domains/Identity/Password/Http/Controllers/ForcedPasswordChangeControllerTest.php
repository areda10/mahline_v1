<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Identity\Password\Http\Controllers;

use App\Domains\Audit\Models\AuditEvent;
use App\Domains\Identity\Authentication\Http\Middleware\EnsureActiveAuthenticationSession;
use App\Domains\Identity\Authentication\Models\AuthenticationSession;
use App\Domains\Identity\Authentication\Services\SessionService;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ForcedPasswordChangeControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureActiveAuthenticationSession::class);
        $this->registerTestAuthenticationRoute();
    }

    public function test_voluntary_password_change_requires_the_current_password_and_updates_the_password(): void
    {
        $user = $this->actor('user', [
            'password' => 'CurrentPassword12!',
            'must_change_password' => false,
        ]);

        $this->actingAs($user)
            ->put(route('password.force-change.update'), [
                'current_password' => 'IncorrectPassword12',
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('CurrentPassword12!', $user->fresh()->password));

        $this->actingAs($user)
            ->put(route('password.force-change.update'), [
                'current_password' => 'CurrentPassword12!',
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ])
            ->assertRedirect(route('user.dashboard'));

        $user->refresh();

        $this->assertTrue(Hash::check('NewPassword123!', $user->password));
        $this->assertFalse($user->must_change_password);
    }

    public function test_forced_password_change_does_not_require_the_current_password_and_clears_the_flag(): void
    {
        $user = $this->actor('user', [
            'password' => 'MahlineMahline',
            'must_change_password' => true,
        ]);

        $this->actingAs($user)
            ->get(route('security.password.edit'))
            ->assertOk()
            ->assertViewIs('identity.password.change');

        $this->actingAs($user)
            ->put(route('password.force-change.update'), [
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ])
            ->assertRedirect(route('user.dashboard'));

        $user->refresh();

        $this->assertTrue(Hash::check('NewPassword123!', $user->password));
        $this->assertFalse($user->must_change_password);
    }

    public function test_forced_password_change_preserves_current_session_and_revokes_other_sessions_and_api_tokens(): void
    {
        $user = $this->actor('user', [
            'password' => 'MahlineMahline',
            'must_change_password' => true,
        ]);

        $this->actingAs($user);

        $sessions = app(SessionService::class);

        $currentSession = $sessions->create(
            $user,
            Str::uuid()->toString(),
            device: 'Current device',
        );

        $otherSession = $sessions->create(
            $user,
            Str::uuid()->toString(),
            device: 'Other device',
        );

        $user->createToken('existing-api-token');

        $this->withSession([
            'authentication_session_id' => (string) $currentSession->getKey(),
        ])->put(route('password.force-change.update'), [
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertRedirect(route('user.dashboard'));

        $this->assertNull(
            $currentSession->fresh()->revoked_at,
            'The session used to complete the forced password change must remain active.',
        );

        $this->assertNotNull(
            $otherSession->fresh()->revoked_at,
            'Other active sessions must be revoked.',
        );

        $this->assertSame(0, $user->fresh()->tokens()->count());

        $this->assertTrue(
            AuthenticationSession::query()
                ->whereKey($currentSession->getKey())
                ->whereNull('revoked_at')
                ->exists(),
        );
    }

    public function test_forced_password_change_blocks_business_routes_until_the_password_is_updated(): void
    {
        $user = $this->actor('user', [
            'password' => 'MahlineMahline',
            'must_change_password' => true,
        ]);

        $this->actingAs($user)
            ->get(route('user.dashboard'))
            ->assertRedirect(route('password.force-change.edit'));

        $this->actingAs($user)
            ->get(route('backoffice.dashboard'))
            ->assertRedirect(route('password.force-change.edit'));
    }

    public function test_forced_password_change_blocks_authenticated_api_routes(): void
    {
        $user = $this->actor('user', [
            'password' => 'MahlineMahline',
            'must_change_password' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/test-auth')
            ->assertForbidden();
    }

    public function test_password_change_returns_each_role_to_its_own_dashboard(): void
    {
        $destinations = [
            'super_admin_root' => 'root.backoffice.dashboard',
            'super_admin' => 'backoffice.dashboard',
            'cooperative_admin' => 'backoffice.dashboard',
            'user' => 'user.dashboard',
        ];

        foreach ($destinations as $role => $route) {
            $user = $this->actor($role, [
                'password' => 'CurrentPassword12!',
                'must_change_password' => false,
            ]);

            $this->actingAs($user)
                ->put(route('password.force-change.update'), [
                    'current_password' => 'CurrentPassword12!',
                    'password' => 'NewPassword123!',
                    'password_confirmation' => 'NewPassword123!',
                ])
                ->assertRedirect(route($route));
        }
    }

    public function test_forced_password_change_returns_each_role_to_its_own_dashboard(): void
    {
        $destinations = [
            'super_admin_root' => 'root.backoffice.dashboard',
            'super_admin' => 'backoffice.dashboard',
            'cooperative_admin' => 'backoffice.dashboard',
            'user' => 'user.dashboard',
        ];

        foreach ($destinations as $role => $route) {
            $user = $this->actor($role, [
                'password' => 'MahlineMahline',
                'must_change_password' => true,
            ]);

            $this->actingAs($user)
                ->put(route('password.force-change.update'), [
                    'password' => 'NewPassword123!',
                    'password_confirmation' => 'NewPassword123!',
                ])
                ->assertRedirect(route($route));

            $this->assertFalse($user->fresh()->must_change_password);
        }
    }

    public function test_password_change_rejects_a_new_password_that_matches_the_current_password(): void
    {
        $user = $this->actor('user', [
            'password' => 'CurrentPassword12!',
            'must_change_password' => false,
        ]);

        $this->actingAs($user)
            ->put(route('password.force-change.update'), [
                'current_password' => 'CurrentPassword12!',
                'password' => 'CurrentPassword12!',
                'password_confirmation' => 'CurrentPassword12!',
            ])
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('CurrentPassword12!', $user->fresh()->password));
    }

    public function test_audit_failure_rolls_back_forced_password_change_and_history(): void
    {
        $user = $this->actor('user', [
            'password' => 'MahlineMahline',
            'must_change_password' => true,
        ]);
        $user->createToken('rollback-test');

        AuditEvent::creating(static fn () => abort(500));
        $this->withoutExceptionHandling();

        try {
            $this->actingAs($user)
                ->put(route('password.force-change.update'), [
                    'password' => 'NewPassword123!',
                    'password_confirmation' => 'NewPassword123!',
                ]);
            $this->fail('Expected audit failure.');
        } catch (\Throwable $exception) {
            Event::forget('eloquent.creating: '.AuditEvent::class);
            $this->assertSame(500, $exception->getStatusCode());
        }

        $user->refresh();
        $this->assertTrue(Hash::check('MahlineMahline', (string) $user->password));
        $this->assertTrue($user->must_change_password);
        $this->assertDatabaseCount('user_password_histories', 0);
        $this->assertSame(1, $user->tokens()->count());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function actor(string $roleSlug, array $attributes = []): User
    {
        $mustChangePassword = (bool) ($attributes['must_change_password'] ?? false);
        unset($attributes['must_change_password']);

        $user = User::factory()->create(array_merge([
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
        ], $attributes));

        $user->must_change_password = $mustChangePassword;
        $user->save();

        $role = Role::query()->firstOrCreate(
            ['slug' => $roleSlug],
            [
                'name' => str($roleSlug)->replace('_', ' ')->title()->toString(),
                'is_active' => true,
            ],
        );

        $user->roles()->syncWithoutDetaching([$role->getKey()]);

        return $user;
    }
}
