<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\Http\Middleware;

use App\Domains\Identity\Authentication\Services\SessionService;
use App\Domains\Identity\Authentication\Services\DeviceTrustService;
use App\Domains\Identity\Authentication\Exceptions\DeviceTrustException;
use App\Domains\Identity\Authentication\Support\DeviceTrustCookie;
use App\Domains\Identity\Authentication\Workflows\AuthenticationWorkflowHook;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class EnsureActiveAuthenticationSession
{
    public function __construct(
        private readonly SessionService $sessions,
        private readonly DeviceTrustService $devices,
        private readonly AuthenticationWorkflowHook $deviceHook,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $authenticationSessionId = $request->session()->get(
            'authentication_session_id',
        );

        $authenticationSession = is_string($authenticationSessionId)
            ? $this->sessions->findActiveById(
                $user,
                $authenticationSessionId,
            )
            : null;

        if (
            ! $user->isAuthorized()
            || $authenticationSession === null
            || ! hash_equals(
                $authenticationSession->session_id,
                $request->session()->getId(),
            )
        ) {
            Auth::logout();

            $request->session()->invalidate();

            $request->session()->regenerateToken();

            return redirect()->route($user->hasRole('customer') ? 'customer.login' : 'authentication.login');
        }

        if ($this->sessions->expireIfInactive($user, $authenticationSession)) {
            Auth::logout();

            $request->session()->invalidate();

            $request->session()->regenerateToken();

            return redirect()
                ->route($user->hasRole('customer') ? 'customer.login' : 'authentication.login')
                ->withErrors([
                    'email' => 'Votre session a expiré après une période d’inactivité.',
                ]);
        }

        try {
            $deviceResolution = $this->devices->ensureSessionDevice(
                user: $user,
                session: $authenticationSession,
                presentedToken: $request->cookie(DeviceTrustCookie::NAME),
                device: $authenticationSession->device,
                browser: $authenticationSession->browser,
            );
        } catch (DeviceTrustException) {
            $this->sessions->revoke($authenticationSession, 'device_proof_rejected');

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route(
                $user->hasRole('customer') ? 'customer.login' : 'authentication.login',
            );
        }

        if ($deviceResolution !== null) {
            $request->attributes->set(
                DeviceTrustCookie::REQUEST_ATTRIBUTE,
                $deviceResolution->cookieToken,
            );

            if ($deviceResolution->isNewDevice) {
                try {
                    $this->deviceHook->afterDeviceRemembered(
                        user: $user,
                        session: $authenticationSession,
                        isNewDevice: true,
                    );
                    $this->deviceHook->afterAuthenticationCommitted(
                        user: $user,
                        session: $authenticationSession,
                        isNewDevice: true,
                    );
                } catch (Throwable $exception) {
                    Log::warning('Unable to record legacy-session device bootstrap notification.', [
                        'user_id' => (string) $user->getKey(),
                        'authentication_session_id' => (string) $authenticationSession->getKey(),
                        'exception' => $exception::class,
                    ]);
                }
            }
        }

        if (! $this->sessions->touch($authenticationSession)) {
            Auth::logout();

            $request->session()->invalidate();

            $request->session()->regenerateToken();

            return redirect()->route($user->hasRole('customer') ? 'customer.login' : 'authentication.login');
        }

        if ($user->must_change_password && ! $request->routeIs(
            'password.force-change.edit',
            'password.force-change.update',
            'security.password.edit',
            'customer.password.force-change.edit',
            'customer.password.force-change.update',
            'customer.security.password.edit',
            'customer.security.password.update',
            'customer.logout',
        )) {
            return redirect()->route($user->hasRole('customer') ? 'customer.password.force-change.edit' : 'password.force-change.edit');
        }

        return $next($request);
    }
}
