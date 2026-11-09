<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Authentication\Services;

use App\Domains\Identity\Authentication\Models\AuthenticationSession;
use App\Domains\Identity\Authentication\Services\SessionService;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SessionInactivityPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_based_inactivity_limits_are_applied(): void
    {
        $service = app(SessionService::class);

        $this->assertSame(15, $service->inactivityTimeoutMinutes($this->userWithRole('super_admin_root')));
        $this->assertSame(30, $service->inactivityTimeoutMinutes($this->userWithRole('super_admin')));
        $this->assertSame(30, $service->inactivityTimeoutMinutes($this->userWithRole('cooperative_admin')));
        $this->assertSame(60, $service->inactivityTimeoutMinutes($this->userWithRole('user')));
    }

    public function test_most_restrictive_timeout_wins_when_user_has_multiple_roles(): void
    {
        $user = $this->userWithRole('user');
        $rootRole = Role::query()->firstOrCreate(
            ['slug' => 'super_admin_root'],
            ['name' => 'Super Admin Root', 'is_active' => true],
        );
        $user->roles()->syncWithoutDetaching([$rootRole->getKey()]);

        $this->assertSame(15, app(SessionService::class)->inactivityTimeoutMinutes($user));
    }

    public function test_session_is_not_expired_before_role_timeout(): void
    {
        $user = $this->userWithRole('super_admin_root');
        $session = $this->sessionFor($user, now()->subMinutes(14)->subSeconds(59));

        $this->assertFalse(app(SessionService::class)->hasExceededInactivityLimit($user, $session));
    }

    public function test_session_expires_at_role_timeout_boundary(): void
    {
        $user = $this->userWithRole('super_admin_root');
        $session = $this->sessionFor($user, now()->subMinutes(15));

        $this->assertTrue(app(SessionService::class)->hasExceededInactivityLimit($user, $session));
    }

    public function test_nullable_last_activity_falls_back_to_authentication_time(): void
    {
        $user = $this->userWithRole('user');
        $session = $this->sessionFor($user, null, now()->subMinutes(60));

        $this->assertTrue(app(SessionService::class)->hasExceededInactivityLimit($user, $session));
    }

    public function test_recent_activity_is_not_expired_for_longer_user_timeout(): void
    {
        $user = $this->userWithRole('user');
        $session = $this->sessionFor($user, now()->subMinutes(59));

        $this->assertFalse(app(SessionService::class)->hasExceededInactivityLimit($user, $session));
    }

    private function userWithRole(string $slug): User
    {
        $user = User::factory()->create([
            'status' => UserStatus::Active,
        ]);

        $role = Role::query()->firstOrCreate(
            ['slug' => $slug],
            [
                'name' => str($slug)->replace('_', ' ')->title()->toString(),
                'is_active' => true,
            ],
        );

        $user->roles()->syncWithoutDetaching([$role->getKey()]);

        return $user;
    }

    private function sessionFor(
        User $user,
        mixed $lastActivityAt,
        mixed $authenticatedAt = null,
    ): AuthenticationSession {
        return AuthenticationSession::query()->create([
            'user_id' => $user->getKey(),
            'session_id' => 'policy-'.str()->random(40),
            'authenticated_at' => $authenticatedAt ?? now()->subHour(),
            'last_activity_at' => $lastActivityAt,
            'revoked_at' => null,
            'revocation_reason' => null,
        ]);
    }
}
