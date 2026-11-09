<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureForcedPasswordChangeCompleted
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->must_change_password) {
            return $next($request);
        }

        if ($request->routeIs(
            'password.force-change.edit',
            'password.force-change.update',
            'security.password.edit',
            'authentication.logout',
        )) {
            return $next($request);
        }

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message' => 'A password change is required before accessing this resource.',
            ], 403);
        }

        return redirect()->route('password.force-change.edit');
    }
}
