<?php

declare(strict_types=1);

use App\Domains\Audit\Http\Middleware\CorrelationIdMiddleware;
use App\Domains\Identity\Authorization\Http\Middleware\EnsureUserHasRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware): void {

        $middleware->redirectGuestsTo('/authentication/login');

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);

        /*
        |--------------------------------------------------------------------------
        | MAHLINE Audit
        |--------------------------------------------------------------------------
        |
        | Attach a unique correlation identifier to every HTTP request.
        |
        */

        $middleware->web(append: [
            CorrelationIdMiddleware::class,
        ]);

        $middleware->api(append: [
            CorrelationIdMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        /*
         * Authentication endpoints return JSON responses.
         *
         * Laravel must therefore render validation exceptions
         * as JSON for the authentication routes as well as
         * the traditional API routes.
         */

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*')
                || $request->is('authentication/*'),
        );

    })
    ->create();
