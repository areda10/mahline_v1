<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Identity\Password\Http\Controllers;

use App\Domains\Identity\Authentication\Http\Middleware\EnsureActiveAuthenticationSession;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class ForcedPasswordChangeControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureActiveAuthenticationSession::class);
    }

    public function test_voluntary_password_change_requires_the_current_password_and_updates_the_password(): void
    {
        $user = $this->actor('user', [
            'password' => 'CurrentPassword12',
            'must_change_password' => false,
        ]);

        $this->actingAs($user)
            ->put(route('password.force-change.update'), [
                'current_password' => 'IncorrectPassword12',
                'password' => 'NewPassword123',
                'password_confirmation' => 'NewPassword123',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('CurrentPassword12', $user->fresh()->password));

        $this->actingAs($user)
            ->put(route('password.force-change.update'), [
                'current_password' => 'CurrentPassword12',
                'password' => 'NewPassword123',
                'password_confirmation' => 'NewPassword123',
            ])
            ->assertRedirect(route('user.dashboard'));

        $user->refresh();

        $this->assertTrue(Hash::check('NewPassword123', $user->password));
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
                'password' => 'NewPassword123',
                'password_confirmation' => 'NewPassword123',
            ])
            ->assertRedirect(route('user.dashboard'));

        $user->refresh();

        $this->assertTrue(Hash::check('NewPassword123', $user->password));
        $this->assertFalse($user->must_change_password);
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
                'password' => 'CurrentPassword12',
                'must_change_password' => false,
            ]);

            $this->actingAs($user)
                ->put(route('password.force-change.update'), [
                    'current_password' => 'CurrentPassword12',
                    'password' => 'NewPassword123',
                    'password_confirmation' => 'NewPassword123',
                ])
                ->assertRedirect(route($route));
        }
    }

    public function test_password_change_rejects_a_new_password_that_matches_the_current_password(): void
    {
        $user = $this->actor('user', [
            'password' => 'CurrentPassword12',
            'must_change_password' => false,
        ]);

        $this->actingAs($user)
            ->put(route('password.force-change.update'), [
                'current_password' => 'CurrentPassword12',
                'password' => 'CurrentPassword12',
                'password_confirmation' => 'CurrentPassword12',
            ])
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('CurrentPassword12', $user->fresh()->password));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function actor(string $roleSlug, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
        ], $attributes));

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
