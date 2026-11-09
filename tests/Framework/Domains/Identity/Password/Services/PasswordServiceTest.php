<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Password\Services;

use App\Domains\Audit\Models\AuditEvent;
use App\Domains\Audit\Services\AuditService;
use App\Domains\Identity\Authentication\Models\AuthenticationSession;
use App\Domains\Identity\Authentication\Services\SessionService;
use App\Domains\Identity\Password\Services\PasswordHistoryService;
use App\Domains\Identity\Password\Services\PasswordService;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Mockery;
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
            'password' => 'MahlinePassword12',
        ]);

        $this->assertTrue(
            $this->passwordService->verifyCurrentPassword(
                user: $user,
                password: 'MahlinePassword12',
            )
        );
    }

    public function test_invalid_current_password_is_rejected(): void
    {
        $user = User::factory()->create([
            'password' => 'MahlinePassword12',
        ]);

        $this->assertFalse(
            $this->passwordService->verifyCurrentPassword(
                user: $user,
                password: 'WrongPassword12',
            )
        );
    }

    public function test_password_can_be_changed(): void
    {
        $user = User::factory()->create([
            'password' => 'MahlinePassword12',
        ]);

        $this->passwordService->changePassword(
            user: $user,
            currentPassword: 'MahlinePassword12',
            newPassword: 'MahlinePassword13!',
        );

        $user->refresh();

        $this->assertTrue(
            Hash::check(
                'MahlinePassword13!',
                (string) $user->password,
            )
        );

        $this->assertFalse(
            Hash::check(
                'MahlinePassword12',
                (string) $user->password,
            )
        );
    }

    public function test_invalid_current_password_prevents_password_change(): void
    {
        $user = User::factory()->create([
            'password' => 'MahlinePassword12',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Current password is invalid.'
        );

        $this->passwordService->changePassword(
            user: $user,
            currentPassword: 'WrongPassword12',
            newPassword: 'MahlinePassword13!',
        );
    }

    public function test_new_password_must_be_different(): void
    {
        $user = User::factory()->create([
            'password' => 'MahlinePassword12',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'New password must be different from current password.'
        );

        $this->passwordService->changePassword(
            user: $user,
            currentPassword: 'MahlinePassword12',
            newPassword: 'MahlinePassword12',
        );
    }

    public function test_password_change_revokes_active_session(): void
    {
        $user = User::factory()->create([
            'password' => 'MahlinePassword12',
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
            currentPassword: 'MahlinePassword12',
            newPassword: 'MahlinePassword13!',
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
            'password' => 'MahlinePassword12',
        ]);

        $this->passwordService->changePassword(
            user: $user,
            currentPassword: 'MahlinePassword12',
            newPassword: 'MahlinePassword13!',
        );

        $user->refresh();

        $this->assertNotSame(
            'MahlinePassword13!',
            $user->password,
        );

        $this->assertTrue(
            Hash::check(
                'MahlinePassword13!',
                (string) $user->password,
            )
        );
    }

    public function test_change_password_rejects_password_shorter_than_12_characters(): void
    {
        $user = User::factory()->create([
            'password' => 'MahlinePassword12',
        ]);

        $this->expectException(InvalidArgumentException::class);

        $this->passwordService->changePassword(
            user: $user,
            currentPassword: 'MahlinePassword12',
            newPassword: 'MahlinePas1',
        );
    }

    public function test_change_password_rejects_password_without_uppercase_letter(): void
    {
        $user = User::factory()->create([
            'password' => 'MahlinePassword12',
        ]);

        $this->expectException(InvalidArgumentException::class);

        $this->passwordService->changePassword(
            user: $user,
            currentPassword: 'MahlinePassword12',
            newPassword: 'mahlinepassword12',
        );
    }

    public function test_change_password_accepts_password_with_spaces(): void
    {
        $user = User::factory()->create([
            'password' => 'MahlinePassword12',
        ]);

        $this->passwordService->changePassword(
            user: $user,
            currentPassword: 'MahlinePassword12',
            newPassword: 'Mahline Password13!',
        );

        $user->refresh();

        $this->assertTrue(
            Hash::check(
                'Mahline Password13!',
                (string) $user->password,
            )
        );
    }

    public function test_old_password_is_unusable_after_password_change(): void
    {
        $user = User::factory()->create([
            'password' => 'MahlinePassword12',
        ]);

        $this->passwordService->changePassword(
            user: $user,
            currentPassword: 'MahlinePassword12',
            newPassword: 'MahlinePassword13!',
        );

        $user->refresh();

        $this->assertFalse(
            $this->passwordService->verifyCurrentPassword(
                user: $user,
                password: 'MahlinePassword12',
            )
        );

        $this->assertTrue(
            $this->passwordService->verifyCurrentPassword(
                user: $user,
                password: 'MahlinePassword13!',
            )
        );
    }

    public function test_password_change_is_recorded_in_audit(): void
    {
        $user = User::factory()->create(['password' => 'MahlinePassword12']);

        $this->passwordService->changePassword(
            user: $user,
            currentPassword: 'MahlinePassword12',
            newPassword: 'MahlinePassword13!',
        );

        $this->assertDatabaseHas('audit_events', [
            'event_key' => 'identity.password.changed',
            'action' => 'changed',
            'subject_type' => User::class,
            'subject_id' => $user->getKey(),
        ]);
    }

    public function test_failed_password_change_does_not_create_audit_event(): void
    {
        $user = User::factory()->create(['password' => 'MahlinePassword12']);

        try {
            $this->passwordService->changePassword(
                user: $user,
                currentPassword: 'WrongPassword12',
                newPassword: 'MahlinePassword13!',
            );
            $this->fail('Expected invalid current password exception.');
        } catch (RuntimeException $exception) {
            $this->assertDatabaseMissing('audit_events', [
                'event_key' => 'identity.password.changed',
                'subject_id' => $user->getKey(),
            ]);
        }
    }

    public function test_password_change_audits_revoked_sessions_and_password_change(): void
    {
        $user = User::factory()->create(['password' => 'MahlinePassword12']);

        $session = AuthenticationSession::query()->create([
            'user_id' => $user->getKey(),
            'session_id' => 'audit-password-session',
            'authenticated_at' => now(),
            'last_activity_at' => now(),
        ]);

        $this->passwordService->changePassword(
            user: $user,
            currentPassword: 'MahlinePassword12',
            newPassword: 'MahlinePassword13!',
        );

        $this->assertDatabaseHas('audit_events', [
            'event_key' => 'identity.authentication.session_revoked',
            'subject_id' => $session->getKey(),
        ]);
        $this->assertDatabaseHas('audit_events', [
            'event_key' => 'identity.password.changed',
            'subject_type' => User::class,
            'subject_id' => $user->getKey(),
        ]);
        $this->assertDatabaseCount('audit_events', 2);
    }

    public function test_password_audit_never_contains_plain_text_passwords(): void
    {
        $user = User::factory()->create(['password' => 'MahlinePassword12']);

        $this->passwordService->changePassword(
            user: $user,
            currentPassword: 'MahlinePassword12',
            newPassword: 'MahlinePassword13!',
        );

        $event = AuditEvent::query()
            ->where('event_key', 'identity.password.changed')
            ->firstOrFail();

        $serialized = json_encode([
            'old_values' => $event->old_values,
            'new_values' => $event->new_values,
            'metadata' => $event->metadata,
        ], JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('MahlinePassword12', $serialized);
        $this->assertStringNotContainsString('MahlinePassword13!', $serialized);
    }

    public function test_audit_failure_rolls_back_password_change_and_history(): void
    {
        $user = User::factory()->create(['password' => 'MahlinePassword12']);
        $user->createToken('rollback-test');

        $audit = Mockery::mock(AuditService::class);
        $audit->shouldReceive('record')->once()
            ->andThrow(new RuntimeException('Simulated audit failure.'));

        $service = new PasswordService(
            app(SessionService::class),
            $audit,
            app(PasswordHistoryService::class),
        );

        try {
            $service->changePassword($user, 'MahlinePassword12', 'MahlinePassword13!');
            $this->fail('Expected audit failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated audit failure.', $exception->getMessage());
        }

        $user->refresh();
        $this->assertTrue(Hash::check('MahlinePassword12', (string) $user->password));
        $this->assertFalse(Hash::check('MahlinePassword13!', (string) $user->password));
        $this->assertDatabaseCount('user_password_histories', 0);
        $this->assertSame(1, $user->tokens()->count());
    }
}
