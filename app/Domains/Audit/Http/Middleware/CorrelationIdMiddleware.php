<?php

declare(strict_types=1);

namespace App\Domains\Audit\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class CorrelationIdMiddleware
{
    public function handle(
        Request $request,
        Closure $next,
    ): Response {
        $correlationId = (string) Str::uuid();

        $request->attributes->set(
            'correlation_id',
            $correlationId,
        );

        $response = $next($request);

        $response->headers->set(
            'X-Correlation-ID',
            $correlationId,
        );

        return $response;
    }
}