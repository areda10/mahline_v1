<?php

declare(strict_types=1);

namespace App\\Domains\\Identity\\Authentication\\Http\\Middleware;

use Closure;
use Illuminate\\Http\\Request;
use Symfony\\Component\\HttpFoundation\\Response;

final class ApplyRoleBasedSessionCookiePolicy
{
    public function handle(Request $request, Closure $next): Response
    {
        /*
         * Keep the authenticated user available for logout responses,
         * because Auth::logout() clears the guard before this middleware
         * receives the response.
         */
        $authenticatedUserBeforeRequest = $request->user();

        $response = $next($request);

        $user = $request->user() ?? $authenticatedUserBeforeRequest;

        /*
         * Root and platform administrators must use browser-session
         * cookies. Cooperative administrators and regular users receive
         * persistent cookies; server-side inactivity remains enforced
         * independently by EnsureActiveAuthenticationSession.
         *
         * This assignment runs after the route/controller but before
         * StartSession adds the session cookie to the response.
         */
        $expireOnClose = $user !== null
            && $user->hasAnyRole([
                'super_admin_root',
                'super_admin',
            ]);

        config([
            'session.expire_on_close' => $expireOnClose,
        ]);

        return $response;
    }
}
