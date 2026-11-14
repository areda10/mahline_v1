<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Authentication\Services;

use App\Domains\Identity\Authentication\Enums\KnownDeviceStatus;
use App\Domains\Identity\Authentication\Exceptions\DeviceTrustException;
use App\Domains\Identity\Authentication\Models\AuthenticationSession;
use App\Domains\Identity\Authentication\Models\KnownDevice;
use App\Domains\Identity\Authentication\Notifications\DeviceTrustRevokedNotification;
use Illuminate\Support\Facades\Notification;
use App\Domains\Identity\Authentication\Services\DeviceTrustService;
use App\Domains\Identity\Authentication\Support\DeviceTrustCookie;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DeviceTrustServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_proof_based_device_is_trusted_and_only_its_hash_is_stored(): void
    {
        $user = User::factory()->create();
        $resolution = app(DeviceTrustService::class)->resolveForAuthentication(
            $user,
            null,
            'Windows',
            'Chrome',
        );

        $this->assertTrue($resolution->isNewDevice);
        $this->assertSame(KnownDeviceStatus::Trusted, $resolution->device->status);
        $this->assertSame(DeviceTrustCookie::hash($resolution->cookieToken), $resolution->device->device_token_hash);
        $this->assertNotSame($resolution->cookieToken, $resolution->device->device_token_hash);
        $this->assertSame('windows', $resolution->device->device);
    }

    public function test_subsequent_device_is_pending_for_exactly_twenty_four_hours(): void
    {
        $user = User::factory()->create();
        $service = app(DeviceTrustService::class);

        $first = $service->resolveForAuthentication($user, null, 'windows', 'chrome');
        $second = $service->resolveForAuthentication($user, null, 'iphone', 'safari');

        $this->assertSame(KnownDeviceStatus::Trusted, $first->device->status);
        $this->assertSame(KnownDeviceStatus::Pending, $second->device->status);
        $this->assertEqualsWithDelta(
            now()->addHours(24)->timestamp,
            $second->device->pending_until->timestamp,
            1,
        );
    }

    public function test_same_browser_proof_resolves_to_the_existing_device(): void
    {
        $user = User::factory()->create();
        $service = app(DeviceTrustService::class);

        $first = $service->resolveForAuthentication($user, null, 'windows', 'chrome');
        $again = $service->resolveForAuthentication($user, $first->cookieToken, 'windows', 'chrome');

        $this->assertFalse($again->isNewDevice);
        $this->assertSame($first->device->getKey(), $again->device->getKey());
        $this->assertSame($first->cookieToken, $again->cookieToken);
    }

    public function test_revoked_device_proof_cannot_authenticate_again(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $service = app(DeviceTrustService::class);
        $resolution = $service->resolveForAuthentication($user, null, 'windows', 'chrome');

        $this->assertTrue($service->revoke($user, $resolution->device));
        Notification::assertSentTo($user, DeviceTrustRevokedNotification::class);

        $this->expectException(DeviceTrustException::class);
        $service->resolveForAuthentication($user, $resolution->cookieToken, 'windows', 'chrome');
    }

    public function test_active_web_session_requires_its_device_proof_cookie(): void
    {
        $user = User::factory()->create();
        $service = app(DeviceTrustService::class);
        $device = $service->resolveForAuthentication($user, null, 'windows', 'chrome');
        $session = $this->createSession($user, $device->device);

        $this->expectException(DeviceTrustException::class);
        $service->ensureSessionDevice($user, $session, null, 'windows', 'chrome');
    }

    public function test_active_web_session_rejects_another_devices_proof_cookie(): void
    {
        $user = User::factory()->create();
        $service = app(DeviceTrustService::class);
        $firstDevice = $service->resolveForAuthentication($user, null, 'windows', 'chrome');
        $secondDevice = $service->resolveForAuthentication($user, null, 'iphone', 'safari');
        $session = $this->createSession($user, $firstDevice->device);

        $this->expectException(DeviceTrustException::class);
        $service->ensureSessionDevice(
            $user,
            $session,
            $secondDevice->cookieToken,
            'iphone',
            'safari',
        );
    }

    public function test_pending_device_remains_usable_for_normal_web_sessions(): void
    {
        $user = User::factory()->create();
        $service = app(DeviceTrustService::class);
        $service->resolveForAuthentication($user, null, 'windows', 'chrome');
        $pending = $service->resolveForAuthentication($user, null, 'iphone', 'safari');
        $session = $this->createSession($user, $pending->device);

        $resolution = $service->ensureSessionDevice(
            $user,
            $session,
            $pending->cookieToken,
            'iphone',
            'safari',
        );

        $this->assertNull($resolution);
        $this->assertSame(KnownDeviceStatus::Pending, $pending->device->fresh()->status);
        $this->assertNull($session->fresh()->revoked_at);
    }

    public function test_revoking_a_device_does_not_revoke_api_tokens(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $issuedToken = $user->createToken('device-revocation-api-token');
        $service = app(DeviceTrustService::class);
        $device = $service->resolveForAuthentication($user, null, 'windows', 'chrome');

        $service->revoke($user, $device->device);

        $this->assertNull($issuedToken->accessToken->fresh()->revoked_at);
    }

    public function test_revoked_device_cannot_keep_an_existing_session_active(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $service = app(DeviceTrustService::class);
        $resolution = $service->resolveForAuthentication($user, null, 'windows', 'chrome');
        $session = $this->createSession($user, $resolution->device);

        $service->revoke($user, $resolution->device);

        $this->expectException(DeviceTrustException::class);
        $service->ensureSessionDevice(
            $user,
            $session,
            $resolution->cookieToken,
            'windows',
            'chrome',
        );
    }

    public function test_legacy_active_session_is_bound_to_a_device_on_its_next_request(): void
    {
        $user = User::factory()->create();
        $service = app(DeviceTrustService::class);
        $session = AuthenticationSession::query()->create([
            'user_id' => $user->getKey(),
            'session_id' => bin2hex(random_bytes(20)),
            'authenticated_at' => now(),
            'last_activity_at' => now(),
            'device' => 'windows',
            'browser' => 'chrome',
        ]);

        $resolution = $service->ensureSessionDevice(
            $user,
            $session,
            null,
            'windows',
            'chrome',
        );

        $this->assertNotNull($resolution);
        $this->assertSame(KnownDeviceStatus::Trusted, $resolution->device->status);
        $this->assertSame($resolution->device->getKey(), $session->fresh()->known_device_id);
        $this->assertSame(
            DeviceTrustCookie::hash($resolution->cookieToken),
            $resolution->device->device_token_hash,
        );
    }

    public function test_pending_device_is_promoted_after_twenty_four_hours_when_a_slot_is_available(): void
    {
        $user = User::factory()->create();
        $service = app(DeviceTrustService::class);
        $service->resolveForAuthentication($user, null, 'windows', 'chrome');
        $pending = $service->resolveForAuthentication($user, null, 'iphone', 'safari');

        $this->travel(24)->hours();

        $this->assertSame(1, $service->promoteExpiredPending());
        $this->assertSame(KnownDeviceStatus::Trusted, $pending->device->fresh()->status);
    }

    public function test_pending_device_stays_pending_when_five_devices_are_already_trusted(): void
    {
        $user = User::factory()->create();
        $service = app(DeviceTrustService::class);

        for ($index = 0; $index < 5; $index++) {
            $this->createTrustedDevice($user, 'trusted-'.$index);
        }

        $pending = $service->resolveForAuthentication($user, null, 'sixth-device', 'chrome');
        $this->travel(24)->hours();

        $this->assertSame(0, $service->promoteExpiredPending());
        $this->assertSame(KnownDeviceStatus::Pending, $pending->device->fresh()->status);
        $this->assertSame(5, $service->trustedCount($user));
    }

    public function test_replacement_is_atomic_and_revokes_only_the_selected_devices_sessions(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $service = app(DeviceTrustService::class);
        $trusted = [];

        for ($index = 0; $index < 5; $index++) {
            $trusted[] = $this->createTrustedDevice($user, 'trusted-'.$index);
        }

        $pending = $service->resolveForAuthentication($user, null, 'sixth-device', 'chrome');
        $selectedSession = $this->createSession($user, $trusted[0]);
        $preservedSession = $this->createSession($user, $trusted[1]);

        $service->replaceTrustedDevice($user, $pending->device, $trusted[0]);

        $this->assertSame(KnownDeviceStatus::NonTrusted, $trusted[0]->fresh()->status);
        $this->assertNotNull($trusted[0]->fresh()->revoked_at);
        $this->assertSame(KnownDeviceStatus::Trusted, $pending->device->fresh()->status);
        $this->assertNotNull($selectedSession->fresh()->revoked_at);
        $this->assertSame('device_replaced', $selectedSession->fresh()->revocation_reason);
        $this->assertNull($preservedSession->fresh()->revoked_at);
        $this->assertSame(5, $service->trustedCount($user));
    }

    public function test_user_and_session_device_relations_are_consistent(): void
    {
        $user = User::factory()->create();
        $device = $this->createTrustedDevice($user, 'relation-device');
        $session = $this->createSession($user, $device);

        $this->assertTrue($device->user->is($user));
        $this->assertTrue($session->knownDevice->is($device));
        $this->assertTrue($user->knownDevices->contains('id', $device->getKey()));
        $this->assertTrue($user->authenticationSessions->contains('id', $session->getKey()));
        $this->assertTrue($device->sessions->contains('id', $session->getKey()));
        $this->assertTrue($device->latestSession->is($session));
    }

    private function createTrustedDevice(User $user, string $token): KnownDevice
    {
        return KnownDevice::query()->create([
            'user_id' => $user->getKey(),
            'device' => $token,
            'device_token_hash' => hash('sha256', str_repeat($token, 4)),
            'status' => KnownDeviceStatus::Trusted,
            'trusted_at' => now(),
            'last_seen_at' => now(),
        ]);
    }

    private function createSession(User $user, KnownDevice $device): AuthenticationSession
    {
        return AuthenticationSession::query()->create([
            'user_id' => $user->getKey(),
            'known_device_id' => $device->getKey(),
            'session_id' => bin2hex(random_bytes(20)),
            'authenticated_at' => now(),
            'last_activity_at' => now(),
        ]);
    }
}
