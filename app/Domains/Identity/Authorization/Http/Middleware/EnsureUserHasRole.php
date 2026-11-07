<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authorization\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureUserHasRole
{
    public function handle(
        Request $request,
        Closure $next,
        string ...$roles,
    ): Response {
        $user = $request->user();

        abort_unless(
            $user !== null
            && $user->isAuthorized()
            && $roles !== []
            && $user->hasAnyRole($roles),
            403,
        );

        return $next($request);
    }
}
