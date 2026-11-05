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
    ) {
    }

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

    $isAuthorized = $user->isAuthorized();

    $sessionId = $request->session()->getId();

    $sessionIdMatches = $authenticationSession !== null
        && hash_equals(
            $authenticationSession->session_id,
            $sessionId,
        );

    dump([
        'middleware_user_id' => $user->getKey(),

        'middleware_user_email' => $user->email,

        'middleware_is_authorized' => $isAuthorized,

        'middleware_authentication_session_id' =>
            $authenticationSessionId,

        'middleware_session_found' =>
            $authenticationSession !== null,

        'middleware_session_id' => $sessionId,

        'middleware_database_session_id' =>
            $authenticationSession?->session_id,

        'middleware_session_id_matches' => $sessionIdMatches,

        'middleware_guard' => Auth::getDefaultDriver(),

        'middleware_authenticated' => Auth::check(),
    ]);

    if (
        ! $isAuthorized
        || $authenticationSession === null
        || ! $sessionIdMatches
    ) {
        Auth::logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('authentication.login');
    }

    $this->sessions->touch($authenticationSession);

    return $next($request);
}
}
