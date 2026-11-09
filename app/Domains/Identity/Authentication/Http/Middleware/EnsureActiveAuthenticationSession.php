<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\Http\Middleware;

use App\Domains\Identity\Authentication\Services\SessionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class EnsureActiveAuthenticationSession
{
    public function __construct(
        private readonly SessionService $sessions,
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

            return redirect()->route('authentication.login');
        }

        if ($this->sessions->expireIfInactive($user, $authenticationSession)) {
            Auth::logout();

            $request->session()->invalidate();

            $request->session()->regenerateToken();

            return redirect()
                ->route('authentication.login')
                ->with('error', 'Votre session a expiré après une période d’inactivité.');
        }

        $this->sessions->touch($authenticationSession);

        if ($user->must_change_password && ! $request->routeIs('password.force-change.edit', 'password.force-change.update', 'security.password.edit')) {
            return redirect()->route('password.force-change.edit');
        }

        return $next($request);
    }
}
