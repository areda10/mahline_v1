<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Audit\Http\Middleware;

use App\Domains\Audit\Http\Middleware\CorrelationIdMiddleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class CorrelationIdMiddlewareTest extends TestCase
{
    public function test_generates_correlation_id_for_request(): void
    {
        $middleware = new CorrelationIdMiddleware;

        $request = Request::create('/test', 'GET');

        $response = $middleware->handle(
            $request,
            function (Request $request): Response {
                $correlationId = $request->attributes->get(
                    'correlation_id',
                );

                self::assertIsString($correlationId);
                self::assertNotEmpty($correlationId);

                return new Response('OK');
            },
        );

        self::assertSame(
            $request->attributes->get('correlation_id'),
            $response->headers->get('X-Correlation-ID'),
        );
    }

    public function test_generates_a_new_id_for_each_request(): void
    {
        $middleware = new CorrelationIdMiddleware;

        $firstRequest = Request::create('/test', 'GET');
        $secondRequest = Request::create('/test', 'GET');

        $next = static fn (): Response => new Response('OK');

        $middleware->handle($firstRequest, $next);
        $middleware->handle($secondRequest, $next);

        self::assertNotSame(
            $firstRequest->attributes->get('correlation_id'),
            $secondRequest->attributes->get('correlation_id'),
        );
    }

    public function test_does_not_trust_client_correlation_id(): void
    {
        $middleware = new CorrelationIdMiddleware;

        $request = Request::create('/test', 'GET');

        $request->headers->set(
            'X-Correlation-ID',
            'client-controlled-id',
        );

        $middleware->handle(
            $request,
            static fn (): Response => new Response('OK'),
        );

        self::assertNotSame(
            'client-controlled-id',
            $request->attributes->get('correlation_id'),
        );
    }
}
