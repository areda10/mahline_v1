<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\Http\Middleware;

use App\Domains\Identity\Authentication\Support\DeviceTrustCookie;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ApplyDeviceTrustCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $token = $request->attributes->get(DeviceTrustCookie::REQUEST_ATTRIBUTE);

        if (! DeviceTrustCookie::isValid(is_string($token) ? $token : null)) {
            return $response;
        }

        $secure = config('session.secure');

        $response->headers->setCookie(cookie(
            DeviceTrustCookie::NAME,
            $token,
            60 * 24 * 400,
            '/',
            config('session.domain'),
            $secure === null ? $request->isSecure() : (bool) $secure,
            true,
            false,
            'lax',
        ));

        return $response;
    }
}
