<?php

declare(strict_types=1);

namespace Tests;

use App\Domains\Identity\Authentication\Http\Middleware\EnsureForcedPasswordChangeCompleted;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

abstract class TestCase extends BaseTestCase
{
    /**
     * Register a protected API endpoint used only by authentication tests.
     *
     * The endpoint is deliberately absent from the production API routes.
     */
    protected function registerTestAuthenticationRoute(): void
    {
        Route::middleware([
            'auth:sanctum',
            EnsureForcedPasswordChangeCompleted::class,
        ])->get('/api/test-auth', static function (Request $request) {
            return response()->json([
                'authenticated' => true,
                'user_id' => $request->user()->getKey(),
            ]);
        });
    }
}
