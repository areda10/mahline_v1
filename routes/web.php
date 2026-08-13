<?php

declare(strict_types=1);

use App\Domains\Identity\Authentication\Http\Controllers\AuthenticationController;
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| MAHLINE web routes.
|
| Authentication routes are delegated to the Identity domain
| instead of containing authentication logic directly in the route.
|
*/

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
*/

// Route::get('/', function () {
//     return view('welcome');
// });


/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
|
| Routes related to user authentication.
|
*/

Route::middleware('guest')->group(function (): void {
    /*
     * Display the login form.
     */
    Route::get('/login', [
        AuthenticationController::class,
        'showLoginForm',
    ])->name('login');

    /*
     * Authenticate the user.
     */
    Route::post('/login', [
        AuthenticationController::class,
        'authenticate',
    ])->name('login.authenticate');
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function (): void {
    /*
     * Logout the currently authenticated user.
     */
    Route::post('/logout', [
        AuthenticationController::class,
        'logout',
    ])->name('logout');
});