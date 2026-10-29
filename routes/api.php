<?php

declare(strict_types=1);

use App\Domains\Audit\Http\Controllers\AuditEventController;
use App\Domains\Identity\Password\Http\Controllers\PasswordResetController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/test-auth', function (Request $request) {
    return response()->json([
        'authenticated' => true,
        'user_id' => $request->user()->getKey(),
    ]);
});

Route::post(
    '/password/forgot',
    [PasswordResetController::class, 'forgot'],
);

Route::post(
    '/password/reset',
    [PasswordResetController::class, 'reset'],
);

Route::middleware('auth:sanctum')->get(
    '/audit/events',
    [AuditEventController::class, 'index'],
)->name('audit.events.index');
