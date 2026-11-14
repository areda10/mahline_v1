<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\Services;

use App\Core\Foundation\Services\BaseService;
use App\Domains\Audit\Services\AuditService;
use App\Domains\Identity\Authentication\DTOs\DeviceTrustResolution;
use App\Domains\Identity\Authentication\Enums\KnownDeviceStatus;
use App\Domains\Identity\Authentication\Exceptions\DeviceTrustException;
use App\Domains\Identity\Authentication\Models\AuthenticationSession;
use App\Domains\Identity\Authentication\Models\KnownDevice;
use App\Domains\Identity\Authentication\Support\DeviceTrustCookie;
use App\Domains\Identity\Authentication\Notifications\DeviceTrustRevokedNotification;
use App\Domains\Identity\Users\Models\User;
use App\Shared\DTOs\AuditEntryDTO;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

final class DeviceTrustService extends BaseService
{
    public const int MAX_TRUSTED_DEVICES = 5;

    public const int PENDING_HOURS = 24;

    public function __construct(
        private readonly SessionService $sessions,
        private readonly AuditService $audit,
    ) {}

    public function resolveForAuthentication(
        User $user,
        ?string $presentedToken,
        ?string $device,
        ?string $browser,
    ): DeviceTrustResolution {
        return DB::transaction(function () use (
            $user,
            $presentedToken,
            $device,
            $browser,
        ): DeviceTrustResolution {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (DeviceTrustCookie::isValid($presentedToken)) {
                $knownDevice = KnownDevice::query()
                    ->where('user_id', $lockedUser->getKey())
                    ->where('device_token_hash', DeviceTrustCookie::hash($presentedToken))
                    ->lockForUpdate()
                    ->first();

                if ($knownDevice !== null) {
                    if ($knownDevice->status === KnownDeviceStatus::NonTrusted) {
                        throw new DeviceTrustException(
                            'This device has been revoked for this account.',
                        );
                    }

                    if (
                        $knownDevice->status === KnownDeviceStatus::Pending
                        && $knownDevice->pending_until?->isPast()
                    ) {
                        $this->promoteIfCapacityAllows($lockedUser, $knownDevice);
                    }

                    $knownDevice->forceFill([
                        'device' => $this->normalizeLabel($device),
                        'browser' => $this->normalizeBrowser($browser),
                        'last_seen_at' => now(),
                    ])->save();

                    return new DeviceTrustResolution(
                        device: $knownDevice->fresh(),
                        cookieToken: $presentedToken,
                        isNewDevice: false,
                    );
                }
            }

            $hasPriorProofDevice = KnownDevice::query()
                ->where('user_id', $lockedUser->getKey())
                ->whereNotNull('device_token_hash')
                ->exists();

            $cookieToken = bin2hex(random_bytes(32));
            $status = ! $hasPriorProofDevice
                ? KnownDeviceStatus::Trusted
                : KnownDeviceStatus::Pending;

            $knownDevice = KnownDevice::query()->create([
                'user_id' => $lockedUser->getKey(),
                'device' => $this->normalizeLabel($device),
                'browser' => $this->normalizeBrowser($browser),
                'device_token_hash' => DeviceTrustCookie::hash($cookieToken),
                'status' => $status,
                'trusted_at' => $status === KnownDeviceStatus::Trusted ? now() : null,
                'pending_until' => $status === KnownDeviceStatus::Pending
                    ? now()->addHours(self::PENDING_HOURS)
                    : null,
                'last_seen_at' => now(),
            ]);

            return new DeviceTrustResolution(
                device: $knownDevice,
                cookieToken: $cookieToken,
                isNewDevice: true,
            );
        }, attempts: 3);
    }

    public function ensureSessionDevice(
        User $user,
        AuthenticationSession $session,
        ?string $presentedToken,
        ?string $device,
        ?string $browser,
    ): ?DeviceTrustResolution {
        return DB::transaction(function () use (
            $user,
            $session,
            $presentedToken,
            $device,
            $browser,
        ): ?DeviceTrustResolution {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedSession = AuthenticationSession::query()
                ->whereKey($session->getKey())
                ->where('user_id', $lockedUser->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedSession->known_device_id !== null) {
                $knownDevice = KnownDevice::query()
                    ->whereKey($lockedSession->known_device_id)
                    ->where('user_id', $lockedUser->getKey())
                    ->lockForUpdate()
                    ->first();

                if ($knownDevice === null) {
                    throw new DeviceTrustException(
                        'The device associated with this session is no longer trusted.',
                    );
                }

                if (
                    ! DeviceTrustCookie::isValid($presentedToken)
                    || $knownDevice->device_token_hash === null
                    || ! hash_equals(
                        $knownDevice->device_token_hash,
                        DeviceTrustCookie::hash((string) $presentedToken),
                    )
                ) {
                    throw new DeviceTrustException(
                        'The browser device proof does not match this authentication session.',
                    );
                }

                if ($knownDevice->status === KnownDeviceStatus::NonTrusted) {
                    throw new DeviceTrustException(
                        'The device associated with this session is no longer trusted.',
                    );
                }

                if (
                    $knownDevice->status === KnownDeviceStatus::Pending
                    && $knownDevice->pending_until?->isPast()
                ) {
                    $this->promoteIfCapacityAllows($lockedUser, $knownDevice);
                }

                if (
                    $knownDevice->last_seen_at === null
                    || $knownDevice->last_seen_at->lessThanOrEqualTo(now()->subMinutes(5))
                ) {
                    $knownDevice->forceFill(['last_seen_at' => now()])->save();
                }

                $session->setRawAttributes($lockedSession->getAttributes(), true);

                return null;
            }

            $resolution = $this->resolveForAuthentication(
                user: $lockedUser,
                presentedToken: $presentedToken,
                device: $device,
                browser: $browser,
            );

            $lockedSession->forceFill([
                'known_device_id' => $resolution->device->getKey(),
            ])->save();

            $session->setRawAttributes($lockedSession->getAttributes(), true);

            return $resolution;
        }, attempts: 3);
    }

    /** @return Collection<int, KnownDevice> */
    public function devicesFor(User $user): Collection
    {
        return KnownDevice::query()
            ->with('latestSession')
            ->where('user_id', $user->getKey())
            ->whereNotNull('device_token_hash')
            ->orderByRaw("CASE status WHEN 'trusted' THEN 0 WHEN 'pending' THEN 1 ELSE 2 END")
            ->orderByDesc('last_seen_at')
            ->get();
    }

    public function trustedCount(User $user): int
    {
        return KnownDevice::query()
            ->where('user_id', $user->getKey())
            ->where('status', KnownDeviceStatus::Trusted->value)
            ->count();
    }

    public function revoke(User $user, KnownDevice $device): bool
    {
        $revoked = DB::transaction(function () use ($user, $device): bool {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedDevice = KnownDevice::query()
                ->whereKey($device->getKey())
                ->where('user_id', $lockedUser->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedDevice->status === KnownDeviceStatus::NonTrusted) {
                return false;
            }

            $oldStatus = $lockedDevice->status->value;
            $lockedDevice->forceFill([
                'status' => KnownDeviceStatus::NonTrusted,
                'pending_until' => null,
                'revoked_at' => now(),
            ])->save();

            $this->revokeDeviceSessions($lockedDevice, 'device_revoked');
            $this->recordDeviceAudit(
                $lockedUser,
                $lockedDevice,
                'identity.authentication.device_revoked',
                'device_revoked',
                $oldStatus,
                KnownDeviceStatus::NonTrusted->value,
            );

            return true;
        }, attempts: 3);

        if ($revoked) {
            $this->notifyDeviceRevoked($user, $device->fresh());
        }

        return $revoked;
    }

    public function replaceTrustedDevice(
        User $user,
        KnownDevice $pendingDevice,
        KnownDevice $trustedDeviceToReplace,
    ): void {
        DB::transaction(function () use (
            $user,
            $pendingDevice,
            $trustedDeviceToReplace,
        ): void {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $pending = KnownDevice::query()
                ->whereKey($pendingDevice->getKey())
                ->where('user_id', $lockedUser->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $target = KnownDevice::query()
                ->whereKey($trustedDeviceToReplace->getKey())
                ->where('user_id', $lockedUser->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $pending->status !== KnownDeviceStatus::Pending
                || $target->status !== KnownDeviceStatus::Trusted
                || $pending->is($target)
            ) {
                throw new DeviceTrustException(
                    'The selected devices cannot be used for a trust replacement.',
                );
            }

            if ($this->trustedCount($lockedUser) < self::MAX_TRUSTED_DEVICES) {
                throw new DeviceTrustException(
                    'A trusted-device replacement is not required because a trusted-device slot is available.',
                );
            }

            $target->forceFill([
                'status' => KnownDeviceStatus::NonTrusted,
                'pending_until' => null,
                'revoked_at' => now(),
            ])->save();

            $this->revokeDeviceSessions($target, 'device_replaced');
            $this->recordDeviceAudit(
                $lockedUser,
                $target,
                'identity.authentication.device_replaced',
                'device_replaced',
                KnownDeviceStatus::Trusted->value,
                KnownDeviceStatus::NonTrusted->value,
            );

            $pending->forceFill([
                'status' => KnownDeviceStatus::Trusted,
                'trusted_at' => now(),
                'pending_until' => null,
                'revoked_at' => null,
            ])->save();

            $this->recordDeviceAudit(
                $lockedUser,
                $pending,
                'identity.authentication.device_trusted',
                'device_trusted',
                KnownDeviceStatus::Pending->value,
                KnownDeviceStatus::Trusted->value,
            );
        }, attempts: 3);

        $this->notifyDeviceRevoked($user, $trustedDeviceToReplace->fresh());
    }

    public function promoteExpiredPending(): int
    {
        $promoted = 0;

        KnownDevice::query()
            ->where('status', KnownDeviceStatus::Pending->value)
            ->whereNotNull('pending_until')
            ->where('pending_until', '<=', now())
            ->select('id', 'user_id')
            ->chunkById(100, function ($devices) use (&$promoted): void {
                foreach ($devices as $candidate) {
                    $didPromote = DB::transaction(function () use ($candidate): bool {
                        $user = User::query()
                            ->whereKey($candidate->user_id)
                            ->lockForUpdate()
                            ->first();

                        if ($user === null) {
                            return false;
                        }

                        $device = KnownDevice::query()
                            ->whereKey($candidate->getKey())
                            ->where('user_id', $user->getKey())
                            ->lockForUpdate()
                            ->first();

                        if (
                            $device === null
                            || $device->status !== KnownDeviceStatus::Pending
                            || $device->pending_until === null
                            || $device->pending_until->isFuture()
                            || $this->trustedCount($user) >= self::MAX_TRUSTED_DEVICES
                        ) {
                            return false;
                        }

                        $device->forceFill([
                            'status' => KnownDeviceStatus::Trusted,
                            'trusted_at' => now(),
                            'pending_until' => null,
                        ])->save();

                        $this->recordDeviceAudit(
                            $user,
                            $device,
                            'identity.authentication.device_trusted',
                            'device_trusted',
                            KnownDeviceStatus::Pending->value,
                            KnownDeviceStatus::Trusted->value,
                        );

                        return true;
                    }, attempts: 3);

                    if ($didPromote) {
                        $promoted++;
                    }
                }
            });

        return $promoted;
    }

    private function promoteIfCapacityAllows(User $user, KnownDevice $device): void
    {
        if ($this->trustedCount($user) >= self::MAX_TRUSTED_DEVICES) {
            return;
        }

        $device->forceFill([
            'status' => KnownDeviceStatus::Trusted,
            'trusted_at' => now(),
            'pending_until' => null,
        ])->save();

        $this->recordDeviceAudit(
            $user,
            $device,
            'identity.authentication.device_trusted',
            'device_trusted',
            KnownDeviceStatus::Pending->value,
            KnownDeviceStatus::Trusted->value,
        );
    }

    private function revokeDeviceSessions(KnownDevice $device, string $reason): void
    {
        AuthenticationSession::query()
            ->where('known_device_id', $device->getKey())
            ->whereNull('revoked_at')
            ->orderBy('id')
            ->get()
            ->each(fn (AuthenticationSession $session) => $this->sessions->revoke($session, $reason));
    }

    private function notifyDeviceRevoked(User $user, ?KnownDevice $device): void
    {
        if ($device === null) {
            return;
        }

        $ipAddress = $device->latestSession()->first()?->ip_address;

        $notification = new DeviceTrustRevokedNotification(
            device: $device->device,
            browser: $device->browser,
            ipAddress: is_string($ipAddress) ? $ipAddress : null,
            revokedAt: $device->revoked_at ?? now(),
        );

        foreach (['database', 'mail'] as $channel) {
            try {
                Notification::sendNow($user, $notification, [$channel]);
            } catch (Throwable $exception) {
                Log::warning('Unable to send device-revocation notification.', [
                    'user_id' => (string) $user->getKey(),
                    'channel' => $channel,
                    'exception' => $exception::class,
                ]);
            }
        }
    }

    private function recordDeviceAudit(
        User $user,
        KnownDevice $device,
        string $eventKey,
        string $action,
        ?string $oldStatus,
        string $newStatus,
    ): void {
        $this->audit->record(new AuditEntryDTO(
            eventKey: $eventKey,
            action: $action,
            actorType: User::class,
            actorId: (string) $user->getKey(),
            cooperativeId: $user->cooperative_id
                ? (string) $user->cooperative_id
                : null,
            subjectType: KnownDevice::class,
            subjectId: (string) $device->getKey(),
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => $newStatus],
            metadata: [
                'device' => $device->device,
                'browser' => $device->browser,
            ],
        ));
    }

    private function normalizeLabel(?string $device): string
    {
        $label = mb_strtolower(trim((string) $device));

        return $label === '' ? 'unknown' : mb_substr($label, 0, 100);
    }

    private function normalizeBrowser(?string $browser): ?string
    {
        $browser = trim((string) $browser);

        return $browser === '' ? null : mb_substr($browser, 0, 100);
    }
}
