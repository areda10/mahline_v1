<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Password\Services;

use App\Domains\Identity\Authentication\Models\AuthenticationSession;
use App\Domains\Identity\Password\Services\PasswordService;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

final class PasswordServiceTest extends TestCase
{
    use RefreshDatabase;

    private PasswordService $passwordService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->passwordService = app(
            PasswordService::class
        );
    }

    public function test_current_password_is_verified(): void
    {
        $user = User::factory()->create([
            'password' => 'current-password',
        ]);

        $this->assertTrue(
            $this->passwordService->verifyCurrentPassword(
                user: $user,
                password: 'current-password',
            )
        );
    }

    public function test_invalid_current_password_is_rejected(): void
    {
        $user = User::factory()->create([
            'password' => 'current-password',
        ]);

        $this->assertFalse(
            $this->passwordService->verifyCurrentPassword(
                user: $user,
                password: 'wrong-password',
            )
        );
    }

    public function test_password_can_be_changed(): void
    {
        $user = User::factory()->create([
            'password' => 'current-password',
        ]);

        $this->passwordService->changePassword(
            user: $user,
            currentPassword: 'current-password',
            newPassword: 'new-password',
        );

        $user->refresh();

        $this->assertTrue(
            Hash::check(
                'new-password',
                (string) $user->password,
            )
        );

        $this->assertFalse(
            Hash::check(
                'current-password',
                (string) $user->password,
            )
        );
    }

    public function test_invalid_current_password_prevents_password_change(): void
    {
        $user = User::factory()->create([
            'password' => 'current-password',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Current password is invalid.'
        );

        $this->passwordService->changePassword(
            user: $user,
            currentPassword: 'wrong-password',
            newPassword: 'new-password',
        );
    }

    public function test_new_password_must_be_different(): void
    {
        $user = User::factory()->create([
            'password' => 'current-password',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'New password must be different from current password.'
        );

        $this->passwordService->changePassword(
            user: $user,
            currentPassword: 'current-password',
            newPassword: 'current-password',
        );
    }

    public function test_password_change_revokes_active_session(): void
    {
        $user = User::factory()->create([
            'password' => 'current-password',
        ]);

        AuthenticationSession::query()->create([
            'user_id' => $user->getKey(),
            'session_id' => 'password-test-session',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'browser' => 'Test Browser',
            'device' => 'Test Device',
            'authenticated_at' => now(),
            'last_activity_at' => now(),
            'revoked_at' => null,
            'revocation_reason' => null,
        ]);

        $this->passwordService->changePassword(
            user: $user,
            currentPassword: 'current-password',
            newPassword: 'new-password',
        );

        $session = AuthenticationSession::query()
            ->where('user_id', $user->getKey())
            ->firstOrFail();

        $this->assertNotNull($session->revoked_at);
        $this->assertSame(
            'password_changed',
            $session->revocation_reason,
        );
    }

    public function test_password_is_never_stored_in_plain_text(): void
    {
        $user = User::factory()->create([
            'password' => 'current-password',
        ]);

        $this->passwordService->changePassword(
            user: $user,
            currentPassword: 'current-password',
            newPassword: 'new-password',
        );

        $user->refresh();

        $this->assertNotSame(
            'new-password',
            $user->password,
        );

        $this->assertTrue(
            Hash::check(
                'new-password',
                (string) $user->password,
            )
        );
    }
}