<?php

declare(strict_types=1);

use App\Domains\Audit\Http\Middleware\CorrelationIdMiddleware;
use App\Domains\Identity\Authentication\Http\Middleware\ApplyRoleBasedSessionCookiePolicy;
use App\Domains\Identity\Authentication\Http\Middleware\EnsureForcedPasswordChangeCompleted;
use App\Domains\Identity\Authorization\Http\Middleware\EnsureUserHasRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

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
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
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
            EnsureForcedPasswordChangeCompleted::class,
            ApplyRoleBasedSessionCookiePolicy::class,
        ]);

        $middleware->api(append: [
            CorrelationIdMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        /*
         * API routes always return JSON. Authentication web routes
         * negotiate JSON only when requested by the client, allowing
         * browser form submissions to use redirects and session errors.
         */

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*')
                || ($request->is('authentication/*') && $request->expectsJson()),
        );

    })
    ->create();
