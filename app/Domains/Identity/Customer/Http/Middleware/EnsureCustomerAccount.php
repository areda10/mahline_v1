<?php

declare(strict_types=1);

namespace App\Domains\Identity\Customer\Http\Middleware;

use App\Domains\Identity\Users\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureCustomerAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless(
            $user instanceof User
            && $user->hasRole('customer')
            && ! $user->hasAnyRole([
                'super_admin_root',
                'super_admin',
                'cooperative_admin',
                'user',
            ]),
            403,
        );

        return $next($request);
    }
}
