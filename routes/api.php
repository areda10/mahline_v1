<?php

declare(strict_types=1);

use App\Domains\Audit\Http\Controllers\AuditEventController;
use App\Domains\Identity\Password\Http\Controllers\PasswordResetController;
use App\Domains\Identity\Authentication\Http\Middleware\EnsureForcedPasswordChangeCompleted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', EnsureForcedPasswordChangeCompleted::class])->get('/test-auth', function (Request $request) {
    return response()->json([
        'authenticated' => true,
        'user_id' => $request->user()->getKey(),
    ]);
});

Route::post(
    '/password/forgot',
    [PasswordResetController::class, 'forgot'],
)->middleware('throttle:5,1');

Route::post(
    '/password/reset',
    [PasswordResetController::class, 'reset'],
)->middleware('throttle:10,1');

Route::middleware(['auth:sanctum', EnsureForcedPasswordChangeCompleted::class])->get(
    '/audit/events',
    [AuditEventController::class, 'index'],
)->name('audit.events.index');
