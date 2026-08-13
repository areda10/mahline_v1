<?php

declare(strict_types=1);

use App\Domains\Identity\Authentication\Http\Controllers\AuthenticationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
|
| Authentication endpoints are grouped here.
|
| The controller is responsible only for the HTTP layer.
| AuthenticationService remains responsible for the authentication
| business logic.
|
*/

Route::prefix('authentication')
    ->name('authentication.')
    ->group(function (): void {

        /*
         * Display the login form.
         *
         * GET /authentication/login
         */
        Route::get(
            '/login',
            [AuthenticationController::class, 'showLogin']
        )->name('login');

        /*
         * Authenticate the user.
         *
         * POST /authentication/login
         */
        Route::post(
            '/login',
            [AuthenticationController::class, 'login']
        )->name('login.store');

        /*
         * Logout the authenticated user.
         *
         * POST /authentication/logout
         */
        Route::post(
            '/logout',
            [AuthenticationController::class, 'logout']
        )
            ->middleware('auth')
            ->name('logout');
    });

/*
|--------------------------------------------------------------------------
| Application Home
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('welcome');
});