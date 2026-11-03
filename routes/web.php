<?php

use App\Domains\Catalog\Http\Controllers\CatalogController;
use App\Domains\Catalog\Http\Controllers\CatalogProductController;
use App\Domains\Categories\Http\Controllers\CategoryController;
use App\Domains\Cooperatives\Http\Controllers\CooperativeController;
use App\Domains\Identity\Authentication\Http\Controllers\AuthenticationController;
use App\Domains\Identity\Authentication\Http\Middleware\EnsureActiveAuthenticationSession;
use App\Domains\Identity\UserManagement\Http\Controllers\SuperAdminCooperativeAssignmentController;
use App\Domains\Identity\UserManagement\Http\Controllers\UserManagementController;
use App\Domains\Identity\Password\Http\Controllers\PasswordResetController;
use App\Domains\Products\Http\Controllers\ProductController;
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
| Password Reset
|--------------------------------------------------------------------------
*/
Route::prefix('password')
    ->name('password.')
    ->middleware('web')
    ->group(function (): void {
        // Display the forgot-password form.
        Route::get(
            '/forgot',
            [PasswordResetController::class, 'showForgotForm']
        )->name('request');

        // Send the password-reset email.
        Route::post(
            '/forgot',
            [PasswordResetController::class, 'forgot']
        )->name('email');

        // Display the password-reset form.
        Route::get(
            '/reset',
            [PasswordResetController::class, 'showResetForm']
        )->name('reset');

        // Apply the new password.
        Route::post(
            '/reset',
            [PasswordResetController::class, 'reset']
        )->name('update');
    });

/*
|--------------------------------------------------------------------------
| Application
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('back-office')
    ->name('backoffice.')
    ->middleware(['web', 'auth', EnsureActiveAuthenticationSession::class])
    ->group(function (): void {

        Route::get('users', [UserManagementController::class, 'index'])
            ->name('users.index');

        Route::get('users/{user}', [UserManagementController::class, 'show'])
            ->name('users.show');

        Route::patch('users/{user}/activate', [UserManagementController::class, 'activate'])
            ->name('users.activate');

        Route::patch('users/{user}/deactivate', [UserManagementController::class, 'deactivate'])
            ->name('users.deactivate');

        Route::prefix('super-admins/{superAdmin}')
            ->name('super-admins.')
            ->group(function (): void {
                Route::get(
                    'cooperatives',
                    [SuperAdminCooperativeAssignmentController::class, 'index'],
                )->name('cooperatives.index');

                Route::post(
                    'cooperatives/{cooperative}',
                    [SuperAdminCooperativeAssignmentController::class, 'store'],
                )->name('cooperatives.store');

                Route::delete(
                    'cooperatives/{cooperative}',
                    [SuperAdminCooperativeAssignmentController::class, 'destroy'],
                )->name('cooperatives.destroy');
            });

        Route::resource('cooperatives', CooperativeController::class)
            ->except(['destroy'])
            ->names('cooperatives');

        Route::delete('cooperatives/{cooperative}', [CooperativeController::class, 'destroy'])
            ->name('cooperatives.destroy');

        foreach (['activate', 'deactivate', 'suspend', 'archive'] as $action) {
            Route::patch(
                'cooperatives/{cooperative}/'.$action,
                [CooperativeController::class, $action],
            )->name('cooperatives.'.$action);
        }

        Route::prefix('cooperatives/{cooperative}')
            ->group(function (): void {

                // Catalogues
                Route::resource('catalogs', CatalogController::class)
                    ->except(['destroy'])
                    ->names('catalogs');

                Route::delete(
                    'catalogs/{catalog}',
                    [CatalogController::class, 'destroy'],
                )->name('catalogs.destroy');

                // Gestion des produits associés à un catalogue
                Route::post(
                    'catalogs/{catalog}/products',
                    [CatalogProductController::class, 'store'],
                )->name('catalogs.products.store');

                Route::delete(
                    'catalogs/{catalog}/products/{product}',
                    [CatalogProductController::class, 'destroy'],
                )->name('catalogs.products.destroy');

                // Catégories
                Route::resource('categories', CategoryController::class)
                    ->except(['show'])
                    ->names('categories');

                // Produits
                Route::resource('products', ProductController::class)
                    ->names('products');

                Route::patch(
                    'products/{product}/reactivate',
                    [ProductController::class, 'reactivate'],
                )->name('products.reactivate');
            });
});

// Route::get('/_debug/auth-session', function () {
//     return response()->json([
//         'authenticated' => auth()->check(),
//         'user_id' => auth()->id(),
//         'laravel_session_id' => session()->getId(),
//         'authentication_session_id' => session()->get(
//             'authentication_session_id'
//         ),
//     ]);
// });