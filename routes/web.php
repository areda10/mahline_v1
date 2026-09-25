<?php

use App\Domains\Identity\Authentication\Http\Controllers\AuthenticationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

Route::prefix('authentication')
    ->name('authentication.')
    ->middleware('web')
    ->group(function (): void {
        /*
         * Display authentication endpoint.
         */
        Route::get(
            '/login',
            [AuthenticationController::class, 'showLogin']
        )->name('login');

        /*
         * Authenticate user.
         */
        Route::post(
            '/login',
            [AuthenticationController::class, 'login']
        )->name('login.store');

        /*
         * Logout authenticated user.
         */
        Route::post(
            '/logout',
            [AuthenticationController::class, 'logout']
        )->name('logout');
    });

/*
|--------------------------------------------------------------------------
| Application
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('welcome');
});