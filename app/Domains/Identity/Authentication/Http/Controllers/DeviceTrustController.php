<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\Http\Controllers;

use App\Domains\Identity\Authentication\Exceptions\DeviceTrustException;
use App\Domains\Identity\Authentication\Models\KnownDevice;
use App\Domains\Identity\Authentication\Services\DeviceTrustService;
use App\Domains\Identity\Authentication\Notifications\DeviceTrustRevokedNotification;
use App\Domains\Identity\Authentication\Services\SessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

final class DeviceTrustController
{
    public function index(
        Request $request,
        DeviceTrustService $devices,
        SessionService $sessions,
    ): View {
        $user = $request->user();
        $authenticationSessionId = $request->session()->get('authentication_session_id');
        $session = is_string($authenticationSessionId)
            ? $sessions->findActiveById($user, $authenticationSessionId)
            : null;

        return view('identity.security.devices.index', [
            'devices' => $devices->devicesFor($user),
            'trustedCount' => $devices->trustedCount($user),
            'currentDeviceId' => $session?->known_device_id,
            'securityNotifications' => $user->notifications()
                ->where('type', DeviceTrustRevokedNotification::class)
                ->latest()
                ->limit(5)
                ->get(),
        ]);
    }

    public function destroy(
        Request $request,
        KnownDevice $device,
        DeviceTrustService $devices,
        SessionService $sessions,
    ): RedirectResponse {
        $user = $request->user();
        $authenticationSessionId = $request->session()->get('authentication_session_id');
        $currentSession = is_string($authenticationSessionId)
            ? $sessions->findActiveById($user, $authenticationSessionId)
            : null;
        $revokingCurrentDevice = $currentSession !== null
            && $currentSession->known_device_id !== null
            && hash_equals((string) $currentSession->known_device_id, (string) $device->getKey());

        $devices->revoke($user, $device);

        if ($revokingCurrentDevice) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route(
                $user->hasRole('customer') ? 'customer.login' : 'authentication.login',
            )->with('success', 'Cet appareil a été révoqué. Vous êtes déconnecté.');
        }

        return redirect()->route($this->devicesRoute($user))
            ->with('success', 'L’appareil a été révoqué et ses sessions Web ont été coupées.');
    }

    public function replace(
        Request $request,
        KnownDevice $device,
        DeviceTrustService $devices,
        SessionService $sessions,
    ): RedirectResponse {
        $user = $request->user();
        $authenticationSessionId = $request->session()->get('authentication_session_id');
        $currentSession = is_string($authenticationSessionId)
            ? $sessions->findActiveById($user, $authenticationSessionId)
            : null;

        abort_if($currentSession?->known_device_id === null, 403);

        $pendingDevice = KnownDevice::query()
            ->whereKey($currentSession->known_device_id)
            ->where('user_id', $user->getKey())
            ->firstOrFail();

        try {
            $devices->replaceTrustedDevice($user, $pendingDevice, $device);
        } catch (DeviceTrustException $exception) {
            return back()->withErrors(['device' => $exception->getMessage()]);
        }

        return redirect()->route($this->devicesRoute($user))
            ->with('success', 'Le remplacement est effectué. Cet appareil est maintenant de confiance.');
    }

    private function devicesRoute(object $user): string
    {
        if ($user->hasRole('customer')) {
            return 'customer.security.devices.index';
        }

        if ($user->hasRole('super_admin_root')) {
            return 'root.backoffice.security.devices.index';
        }

        return 'backoffice.security.devices.index';
    }
}
