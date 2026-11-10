<?php

declare(strict_types=1);

use App\Domains\Audit\Http\Controllers\AuditEventController;
use App\Domains\Identity\Authentication\Http\Controllers\ApiTokenController;
use App\Domains\Identity\Authentication\Http\Middleware\EnsureForcedPasswordChangeCompleted;
use App\Domains\Identity\SuperAdminRoot\Http\Controllers\UserApiTokenController;
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

Route::middleware(['auth:sanctum', EnsureForcedPasswordChangeCompleted::class])
    ->prefix('identity')
    ->name('identity.')
    ->group(function (): void {
        Route::get('/tokens', [ApiTokenController::class, 'index'])
            ->name('tokens.index');
        Route::post('/tokens', [ApiTokenController::class, 'store'])
            ->name('tokens.store');
        Route::delete('/tokens', [ApiTokenController::class, 'destroyAll'])
            ->name('tokens.destroy-all');
        Route::delete('/tokens/{token}', [ApiTokenController::class, 'destroy'])
            ->name('tokens.destroy');

        Route::prefix('root/users/{user}/tokens')
            ->name('root.users.tokens.')
            ->middleware('role:super_admin_root')
            ->group(function (): void {
                Route::get('/', [UserApiTokenController::class, 'index'])
                    ->name('index');
                Route::delete('/', [UserApiTokenController::class, 'destroyAll'])
                    ->name('destroy-all');
                Route::delete('/{token}', [UserApiTokenController::class, 'destroy'])
                    ->name('destroy');
            });
    });

Route::middleware(['auth:sanctum', EnsureForcedPasswordChangeCompleted::class])->get(
    '/audit/events',
    [AuditEventController::class, 'index'],
)->name('audit.events.index');
