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
        // Generate a server-side unique identifier for this request.
        $correlationId = (string) Str::uuid();

        // Make the identifier available to downstream services.
        $request->attributes->set(
            'correlation_id',
            $correlationId,
        );

        // Continue request processing.
        $response = $next($request);

        // Return the identifier to the client.
        $response->headers->set(
            'X-Correlation-ID',
            $correlationId,
        );

        return $response;
    }
}
