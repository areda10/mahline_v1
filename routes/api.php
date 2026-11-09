<?php

declare(strict_types=1);

use App\Domains\Audit\Http\Controllers\AuditEventController;
use App\Domains\Identity\Authentication\Http\Middleware\EnsureForcedPasswordChangeCompleted;
use App\Domains\Identity\Password\Http\Controllers\PasswordResetController;
use Illuminate\Support\Facades\Route;

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
