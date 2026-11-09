<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Identity\Authentication\Http\Controllers;

use App\Domains\Identity\Authentication\Http\Middleware\EnsureActiveAuthenticationSession;
use App\Domains\Identity\Authentication\Services\AuthenticationSecurityService;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AuthenticationLockoutControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureActiveAuthenticationSession::class);
    }

    public function test_root_can_unlock_account_ip_and_ip_email_counters_and_audit_the_action(): void
    {
        $root = $this->actor('super_admin_root');
        $target = User::factory()->create(['email' => 'locked@example.com']);
        $service = app(AuthenticationSecurityService::class);
        $ipAddress = '203.0.113.45';
        $otherEmail = 'other@example.com';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $service->recordFailedAttempt($otherEmail);
            $service->recordFailedAttemptByIpAndEmail($ipAddress, $otherEmail);
        }

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $service->recordFailedAttempt($target->email);
            $service->recordFailedAttemptByIpAndEmail($ipAddress, $target->email);
        }

        for ($attempt = 0; $attempt < 30; $attempt++) {
            $service->recordFailedAttemptByIp($ipAddress);
        }

        self::assertTrue($service->isLocked($target->email));
        self::assertTrue($service->isIpLocked($ipAddress));
        self::assertTrue($service->isIpEmailLocked($ipAddress, $target->email));

        $this->actingAs($root)
            ->post(route('root.backoffice.security.authentication-lockouts.unlock'), [
                'email' => $target->email,
                'ip_address' => $ipAddress,
                'reason' => 'Déblocage après vérification administrative.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        self::assertFalse($service->isLocked($target->email));
        self::assertFalse($service->isIpLocked($ipAddress));
        self::assertFalse($service->isIpEmailLocked($ipAddress, $target->email));
        self::assertTrue($service->isLocked($otherEmail));
        self::assertTrue($service->isIpEmailLocked($ipAddress, $otherEmail));

        $this->assertDatabaseHas('audit_events', [
            'event_key' => 'identity.authentication.lockouts.unlocked',
            'actor_id' => $root->getKey(),
            'subject_id' => $target->getKey(),
        ]);

        $event = AuditEvent::query()
            ->where('event_key', 'identity.authentication.lockouts.unlocked')
            ->firstOrFail();
        self::assertSame(
            hash_hmac('sha256', $target->email, (string) config('app.key')),
            $event->metadata['email_fingerprint'],
        );
        self::assertSame(
            hash_hmac('sha256', $ipAddress, (string) config('app.key')),
            $event->metadata['ip_fingerprint'],
        );
        self::assertArrayNotHasKey('email', $event->metadata);
        self::assertArrayNotHasKey('ip_address', $event->metadata);
    }

    public function test_non_root_user_cannot_unlock_authentication_counters(): void
    {
        $superAdmin = $this->actor('super_admin');

        $this->actingAs($superAdmin)
            ->post(route('root.backoffice.security.authentication-lockouts.unlock'), [
                'email' => 'locked@example.com',
                'ip_address' => '203.0.113.45',
                'reason' => 'Déblocage administratif demandé.',
            ])
            ->assertForbidden();
    }

    public function test_root_unlock_requires_email_ip_address_and_reason(): void
    {
        $root = $this->actor('super_admin_root');

        $this->actingAs($root)
            ->from(route('root.backoffice.dashboard'))
            ->post(route('root.backoffice.security.authentication-lockouts.unlock'), [
                'email' => 'not-an-email',
                'ip_address' => 'invalid-ip',
                'reason' => 'court',
            ])
            ->assertSessionHasErrors(['email', 'ip_address', 'reason']);
    }

    private function actor(string $roleSlug): User
    {
        $actor = User::factory()->create();

        $role = Role::query()->firstOrCreate(
            ['slug' => $roleSlug],
            [
                'name' => str($roleSlug)->replace('_', ' ')->title()->toString(),
                'is_active' => true,
            ],
        );

        $actor->roles()->syncWithoutDetaching([$role->getKey()]);

        return $actor;
    }
}
