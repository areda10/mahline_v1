<?php

use App\Domains\Catalog\Http\Controllers\CatalogController;
use App\Domains\Catalog\Http\Controllers\CatalogProductController;
use App\Domains\Categories\Http\Controllers\CategoryController;
use App\Domains\Cooperatives\Http\Controllers\CooperativeController;
use App\Domains\Dashboard\Http\Controllers\BackOfficeDashboardController;
use App\Domains\Dashboard\Http\Controllers\UserDashboardController;
use App\Domains\Identity\Authentication\Http\Controllers\ApiTokenManagementController;
use App\Domains\Identity\Authentication\Http\Controllers\AuthenticationController;
use App\Domains\Identity\Customer\Http\Controllers\CustomerDashboardController;
use App\Domains\Identity\Customer\Http\Controllers\CustomerRegistrationController;
use App\Domains\Identity\Authentication\Http\Controllers\AuthenticationLockoutController;
use App\Domains\Identity\Authentication\Http\Controllers\AuthenticationSessionController;
use App\Domains\Identity\Authentication\Http\Middleware\EnsureActiveAuthenticationSession;
use App\Domains\Identity\Password\Http\Controllers\ForcedPasswordChangeController;
use App\Domains\Identity\Password\Http\Controllers\PasswordResetController;
use App\Domains\Identity\SuperAdminRoot\Http\Controllers\CatalogController as SuperAdminRootCatalogController;
use App\Domains\Identity\SuperAdminRoot\Http\Controllers\CatalogProductController as SuperAdminRootCatalogProductController;
use App\Domains\Identity\SuperAdminRoot\Http\Controllers\CooperativeCategoryController as SuperAdminRootCooperativeCategoryController;
use App\Domains\Identity\SuperAdminRoot\Http\Controllers\CooperativeController as SuperAdminRootCooperativeController;
use App\Domains\Identity\SuperAdminRoot\Http\Controllers\DashboardController as SuperAdminRootDashboardController;
use App\Domains\Identity\SuperAdminRoot\Http\Controllers\GlobalCategoryController;
use App\Domains\Identity\SuperAdminRoot\Http\Controllers\ProductController as SuperAdminRootProductController;
use App\Domains\Identity\SuperAdminRoot\Http\Controllers\UserApiTokenController;
use App\Domains\Identity\SuperAdminRoot\Http\Controllers\UserManagementController as SuperAdminRootUserManagementController;
use App\Domains\Identity\UserManagement\Http\Controllers\SuperAdminCooperativeAssignmentController;
use App\Domains\Identity\UserManagement\Http\Controllers\UserInvitationController;
use App\Domains\Identity\UserManagement\Http\Controllers\UserManagementController;
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
| Customer registration and email verification
|--------------------------------------------------------------------------
*/
Route::prefix('customer')
    ->name('customer.')
    ->middleware('web')
    ->group(function (): void {
        Route::middleware('guest')->group(function (): void {
            Route::get('register', [CustomerRegistrationController::class, 'create'])
                ->name('register');
            Route::post('register', [CustomerRegistrationController::class, 'store'])
                ->middleware('throttle:5,1')
                ->name('register.store');
        });

        Route::get('verification/notice', [CustomerRegistrationController::class, 'notice'])
            ->name('verification.notice');
        Route::get('verification/verify', [CustomerRegistrationController::class, 'verify'])
            ->middleware('throttle:10,1')
            ->name('verification.verify');
        Route::post('verification/resend', [CustomerRegistrationController::class, 'resend'])
            ->middleware('throttle:10,1')
            ->name('verification.resend');
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
        )->middleware('throttle:5,1')->name('email');

        // Display the password-reset form.
        Route::get(
            '/reset',
            [PasswordResetController::class, 'showResetForm']
        )->name('reset');

        // Apply the new password.
        Route::post(
            '/reset',
            [PasswordResetController::class, 'reset']
        )->middleware('throttle:10,1')->name('update');
    });

Route::prefix('password/change-required')
    ->name('password.force-change.')
    ->middleware(['web', 'auth', EnsureActiveAuthenticationSession::class])
    ->group(function (): void {
        Route::get('/', [ForcedPasswordChangeController::class, 'edit'])->name('edit');
        Route::put('/', [ForcedPasswordChangeController::class, 'update'])->name('update');
    });

Route::get('/security/password', [ForcedPasswordChangeController::class, 'edit'])
    ->middleware(['web', 'auth', EnsureActiveAuthenticationSession::class])
    ->name('security.password.edit');

/*
|--------------------------------------------------------------------------
| Application
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    if (! auth()->check()) {
        return view('welcome');
    }

    $user = auth()->user();

    if ($user->must_change_password) {
        return redirect()->route('password.force-change.edit');
    }

    if ($user->hasRole('super_admin_root')) {
        return redirect()->route('root.backoffice.dashboard');
    }

    if ($user->hasRole('customer')) {
        return redirect()->route('customer.dashboard');
    }

    if ($user->hasRole('user')) {
        return redirect()->route('user.dashboard');
    }

    if ($user->hasAnyRole(['super_admin', 'cooperative_admin'])) {
        return redirect()->route('backoffice.dashboard');
    }

    abort(403);
});

Route::get('/user/dashboard', UserDashboardController::class)
    ->middleware(['web', 'auth', EnsureActiveAuthenticationSession::class, 'role:user'])
    ->name('user.dashboard');

Route::get('/customer/dashboard', CustomerDashboardController::class)
    ->middleware(['web', 'auth', EnsureActiveAuthenticationSession::class, 'role:customer'])
    ->name('customer.dashboard');

Route::get('/user/invitation/accept', [UserInvitationController::class, 'show'])
    ->middleware('throttle:10,1')
    ->name('user-invitations.show');

Route::post('/user/invitation/accept', [UserInvitationController::class, 'accept'])
    ->middleware('throttle:5,1')
    ->name('user-invitations.accept');

Route::prefix('root/back-office')
    ->name('root.backoffice.')
    ->middleware(['web', 'auth', EnsureActiveAuthenticationSession::class, 'role:super_admin_root'])
    ->group(function (): void {
        Route::get('dashboard', SuperAdminRootDashboardController::class)
            ->name('dashboard');

        Route::get('security/authentication-lockouts', [AuthenticationLockoutController::class, 'index'])
            ->name('security.authentication-lockouts.index');
        Route::post('security/authentication-lockouts/unlock', [AuthenticationLockoutController::class, 'unlock'])
            ->name('security.authentication-lockouts.unlock');
        Route::post('security/authentication-lockouts/emergency-lock', [AuthenticationLockoutController::class, 'emergencyLock'])
            ->name('security.authentication-lockouts.emergency-lock');
        Route::post('security/authentication-lockouts/emergency-unlock', [AuthenticationLockoutController::class, 'releaseEmergencyLock'])
            ->name('security.authentication-lockouts.emergency-unlock');

        Route::get('security/sessions', [AuthenticationSessionController::class, 'rootIndex'])
            ->name('security.sessions.index');
        Route::post('security/sessions/extend', [AuthenticationSessionController::class, 'extend'])
            ->name('security.sessions.extend');
        Route::post('security/sessions/logout-everywhere', [AuthenticationController::class, 'logoutEverywhere'])
            ->name('security.sessions.logout-everywhere');
        Route::delete('security/sessions/{session}', [AuthenticationSessionController::class, 'revokeOwn'])
            ->name('security.sessions.destroy');

        Route::get('security/api-tokens', [ApiTokenManagementController::class, 'index'])
            ->name('security.api-tokens.index');
        Route::post('security/api-tokens', [ApiTokenManagementController::class, 'store'])
            ->name('security.api-tokens.store');
        Route::delete('security/api-tokens', [ApiTokenManagementController::class, 'destroyAll'])
            ->name('security.api-tokens.destroy-all');
        Route::delete('security/api-tokens/{token}', [ApiTokenManagementController::class, 'destroy'])
            ->name('security.api-tokens.destroy');

        Route::delete('users/{user}/sessions/{session}', [AuthenticationSessionController::class, 'revokeForUser'])
            ->name('users.sessions.destroy');

        Route::delete('users/{user}/api-tokens', [UserApiTokenController::class, 'destroyAllFromBackOffice'])
            ->name('users.api-tokens.destroy-all');
        Route::delete('users/{user}/api-tokens/{token}', [UserApiTokenController::class, 'destroyFromBackOffice'])
            ->name('users.api-tokens.destroy');

        Route::get('users', [SuperAdminRootUserManagementController::class, 'index'])
            ->name('users.index');
        Route::get('users/create', [SuperAdminRootUserManagementController::class, 'create'])
            ->name('users.create');
        Route::get('users/{user}/edit', [SuperAdminRootUserManagementController::class, 'edit'])
            ->name('users.edit');
        Route::put('users/{user}', [SuperAdminRootUserManagementController::class, 'update'])
            ->name('users.update');
        Route::post('users', [SuperAdminRootUserManagementController::class, 'store'])
            ->name('users.store');
        Route::get('users/{user}', [SuperAdminRootUserManagementController::class, 'show'])
            ->name('users.show');
        Route::patch('users/{user}/activate', [SuperAdminRootUserManagementController::class, 'activate'])
            ->name('users.activate');
        Route::patch('users/{user}/activate-and-verify', [SuperAdminRootUserManagementController::class, 'activateAndVerify'])
            ->name('users.activate-and-verify');
        Route::patch('users/{user}/deactivate', [SuperAdminRootUserManagementController::class, 'deactivate'])
            ->name('users.deactivate');
        Route::patch('users/{user}/suspend', [SuperAdminRootUserManagementController::class, 'suspend'])
            ->name('users.suspend');
        Route::patch('users/{user}/archive', [SuperAdminRootUserManagementController::class, 'archive'])
            ->name('users.archive');
        Route::patch('users/{user}/restore', [SuperAdminRootUserManagementController::class, 'restore'])
            ->name('users.restore');
        Route::delete('users/{user}', [SuperAdminRootUserManagementController::class, 'destroy'])
            ->name('users.destroy');
        Route::patch('users/{user}/restore-deleted', [SuperAdminRootUserManagementController::class, 'restoreDeleted'])
            ->name('users.restore-deleted');
        Route::post('users/{user}/resend-invitation', [SuperAdminRootUserManagementController::class, 'resendInvitation'])
            ->name('users.resend-invitation');

        Route::get('users/{superAdmin}/cooperatives', [SuperAdminCooperativeAssignmentController::class, 'index'])
            ->name('users.cooperatives.index');
        Route::post('users/{superAdmin}/cooperatives/{cooperative}', [SuperAdminCooperativeAssignmentController::class, 'store'])
            ->name('users.cooperatives.store');
        Route::delete('users/{superAdmin}/cooperatives/{cooperative}', [SuperAdminCooperativeAssignmentController::class, 'destroy'])
            ->name('users.cooperatives.destroy');

        Route::resource('cooperatives', SuperAdminRootCooperativeController::class)
            ->except(['destroy'])
            ->names('cooperatives');

        Route::delete('cooperatives/{cooperative}', [SuperAdminRootCooperativeController::class, 'destroy'])
            ->name('cooperatives.destroy');

        foreach (['activate', 'deactivate', 'suspend', 'archive'] as $action) {
            Route::patch(
                'cooperatives/{cooperative}/'.$action,
                [SuperAdminRootCooperativeController::class, $action],
            )->name('cooperatives.'.$action);
        }

        Route::prefix('cooperatives/{cooperative}')
            ->name('cooperatives.')
            ->group(function (): void {
                Route::resource(
                    'categories',
                    SuperAdminRootCooperativeCategoryController::class,
                )
                    ->except(['show'])
                    ->names('categories');

                Route::resource(
                    'catalogs',
                    SuperAdminRootCatalogController::class,
                )
                    ->except(['destroy'])
                    ->names('catalogs');

                Route::delete(
                    'catalogs/{catalog}',
                    [SuperAdminRootCatalogController::class, 'destroy'],
                )->name('catalogs.destroy');

                Route::post(
                    'catalogs/{catalog}/products',
                    [SuperAdminRootCatalogProductController::class, 'store'],
                )->name('catalogs.products.store');

                Route::delete(
                    'catalogs/{catalog}/products/{product}',
                    [SuperAdminRootCatalogProductController::class, 'destroy'],
                )->name('catalogs.products.destroy');

                Route::resource(
                    'products',
                    SuperAdminRootProductController::class,
                )->names('products');

                Route::patch(
                    'products/{product}/reactivate',
                    [SuperAdminRootProductController::class, 'reactivate'],
                )->name('products.reactivate');
            });

        Route::resource('categories', GlobalCategoryController::class)
            ->except(['show'])
            ->names('categories');
    });

/*
|--------------------------------------------------------------------------
| Personal security sessions
|--------------------------------------------------------------------------
|
| Every authenticated active user may manage only their own web sessions.
| The controller enforces session ownership; this group does not grant
| access to back-office user or cooperative management routes.
|
*/
Route::prefix('back-office')
    ->name('backoffice.')
    ->middleware(['web', 'auth', EnsureActiveAuthenticationSession::class, 'role:super_admin,cooperative_admin,user'])
    ->group(function (): void {
        Route::get('security/sessions', [AuthenticationSessionController::class, 'index'])
            ->name('security.sessions.index');
        Route::post('security/sessions/extend', [AuthenticationSessionController::class, 'extend'])
            ->name('security.sessions.extend');
        Route::post('security/sessions/logout-everywhere', [AuthenticationController::class, 'logoutEverywhere'])
            ->name('security.sessions.logout-everywhere');
        Route::delete('security/sessions/{session}', [AuthenticationSessionController::class, 'revokeOwn'])
            ->name('security.sessions.destroy');

        Route::get('security/api-tokens', [ApiTokenManagementController::class, 'index'])
            ->name('security.api-tokens.index');
        Route::post('security/api-tokens', [ApiTokenManagementController::class, 'store'])
            ->name('security.api-tokens.store');
        Route::delete('security/api-tokens', [ApiTokenManagementController::class, 'destroyAll'])
            ->name('security.api-tokens.destroy-all');
        Route::delete('security/api-tokens/{token}', [ApiTokenManagementController::class, 'destroy'])
            ->name('security.api-tokens.destroy');
    });

Route::prefix('back-office')
    ->name('backoffice.')
    ->middleware(['web', 'auth', EnsureActiveAuthenticationSession::class, 'role:super_admin,cooperative_admin'])
    ->group(function (): void {

        Route::get('dashboard', BackOfficeDashboardController::class)
            ->name('dashboard');

        Route::get('users', [UserManagementController::class, 'index'])
            ->name('users.index');

        Route::get('users/create', [UserManagementController::class, 'create'])
            ->name('users.create');

        Route::post('users', [UserManagementController::class, 'store'])
            ->name('users.store');

        Route::get('users/{user}', [UserManagementController::class, 'show'])
            ->name('users.show');

        Route::patch('users/{user}/activate', [UserManagementController::class, 'activate'])
            ->name('users.activate');

        Route::patch('users/{user}/deactivate', [UserManagementController::class, 'deactivate'])
            ->name('users.deactivate');

        Route::patch('users/{user}/suspend', [UserManagementController::class, 'suspend'])
            ->name('users.suspend');

        Route::patch('users/{user}/archive', [UserManagementController::class, 'archive'])
            ->name('users.archive');

        Route::patch('users/{user}/restore', [UserManagementController::class, 'restore'])
            ->name('users.restore');

        Route::delete('users/{user}', [UserManagementController::class, 'destroy'])
            ->name('users.destroy');

        Route::patch('users/{user}/restore-deleted', [UserManagementController::class, 'restoreDeleted'])
            ->name('users.restore-deleted');

        Route::post('users/{user}/resend-invitation', [UserManagementController::class, 'resendInvitation'])
            ->name('users.resend-invitation');

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
