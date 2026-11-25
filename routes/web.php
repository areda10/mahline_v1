<?php

use App\Domains\Catalog\Http\Controllers\CatalogController;
use App\Domains\Catalog\Http\Controllers\CatalogProductController;
use App\Domains\Categories\Http\Controllers\CategoryController;
use App\Domains\Cooperatives\Http\Controllers\CooperativeController;
use App\Domains\Dashboard\Http\Controllers\BackOfficeDashboardController;
use App\Domains\Dashboard\Http\Controllers\UserDashboardController;
use App\Domains\Identity\Authentication\Http\Controllers\ApiTokenManagementController;
use App\Domains\Identity\Authentication\Http\Controllers\AuthenticationController;
use App\Domains\Identity\Authentication\Http\Controllers\AuthenticationLockoutController;
use App\Domains\Identity\Authentication\Http\Controllers\AuthenticationSessionController;
use App\Domains\Identity\Authentication\Http\Controllers\DeviceTrustController;
use App\Domains\Identity\Authentication\Http\Middleware\EnsureActiveAuthenticationSession;
use App\Domains\Identity\Customer\Http\Controllers\CustomerDashboardController;
use App\Domains\Identity\Customer\Http\Controllers\CustomerProfileController;
use App\Domains\Identity\Customer\Http\Controllers\CustomerRegistrationController;
use App\Domains\Identity\Customer\Http\Middleware\EnsureCustomerAccount;
use App\Domains\Identity\Password\Http\Controllers\ForcedPasswordChangeController;
use App\Domains\Identity\Password\Http\Controllers\PasswordResetController;
use App\Domains\Identity\SuperAdminRoot\Http\Controllers\CatalogController as SuperAdminRootCatalogController;
use App\Domains\Identity\SuperAdminRoot\Http\Controllers\CatalogProductController as SuperAdminRootCatalogProductController;
use App\Domains\Identity\SuperAdminRoot\Http\Controllers\CooperativeCategoryController as SuperAdminRootCooperativeCategoryController;
use App\Domains\Identity\SuperAdminRoot\Http\Controllers\CooperativeController as SuperAdminRootCooperativeController;
use App\Domains\Identity\SuperAdminRoot\Http\Controllers\DashboardController as SuperAdminRootDashboardController;
use App\Domains\Identity\SuperAdminRoot\Http\Controllers\GlobalCategoryController;
use App\Domains\Identity\SuperAdminRoot\Http\Controllers\ProductController as SuperAdminRootProductController;
use App\Domains\Identity\SuperAdminRoot\Http\Controllers\ProductVariantController as SuperAdminRootProductVariantController;
use App\Domains\Identity\SuperAdminRoot\Http\Controllers\UserApiTokenController;
use App\Domains\Identity\SuperAdminRoot\Http\Controllers\UserManagementController as SuperAdminRootUserManagementController;
use App\Domains\Identity\UserManagement\Http\Controllers\SuperAdminCooperativeAssignmentController;
use App\Domains\Identity\UserManagement\Http\Controllers\UserInvitationController;
use App\Domains\Identity\UserManagement\Http\Controllers\UserManagementController;
use App\Domains\Identity\Users\Http\Controllers\PersonalAddressController;
use App\Domains\Inventory\Http\Controllers\InventoryCountController;
use App\Domains\Inventory\Http\Controllers\InventoryCountWorkspaceController;
use App\Domains\Inventory\Http\Controllers\InventoryQuarantineController;
use App\Domains\Inventory\Http\Controllers\InventoryReportController;
use App\Domains\Inventory\Http\Controllers\InventorySecurityReportController;
use App\Domains\Inventory\Http\Controllers\InventoryStockController;
use App\Domains\Inventory\Http\Controllers\InventoryThresholdController;
use App\Domains\Inventory\Http\Controllers\InventoryWorkspaceController;
use App\Domains\Media\Http\Controllers\MediaController;
use App\Domains\Media\Http\Controllers\MediaIncidentController;
use App\Domains\Notifications\Http\Controllers\NotificationCenterController;
use App\Domains\Orders\Http\Controllers\CooperativeOrderController;
use App\Domains\Orders\Http\Controllers\CustomerOrderHistoryController;
use App\Domains\Orders\Http\Controllers\OrderAvailabilityController;
use App\Domains\Orders\Http\Controllers\OrderSafetyHoldController;
use App\Domains\Products\Http\Controllers\ProductController;
use App\Domains\Products\Http\Controllers\ProductStorefrontController;
use App\Domains\Sales\Http\Controllers\SalesCartController;
use App\Domains\Sales\Http\Controllers\SalesCartPageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

Route::prefix('authentication')
    ->name('authentication.')
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
    ->group(function (): void {
        Route::middleware('guest')->group(function (): void {
            Route::get('login', [AuthenticationController::class, 'showCustomerLogin'])
                ->name('login');
            Route::post('login', [AuthenticationController::class, 'loginCustomer'])
                ->middleware('throttle:5,1')
                ->name('login.store');
            Route::get('register', [CustomerRegistrationController::class, 'create'])
                ->name('register');
            Route::post('register', [CustomerRegistrationController::class, 'store'])
                ->middleware('throttle:5,1')
                ->name('register.store');

            Route::get('password/forgot', [PasswordResetController::class, 'showForgotForm'])
                ->name('password.request');
            Route::post('password/forgot', [PasswordResetController::class, 'forgot'])
                ->middleware('throttle:5,1')
                ->name('password.email');
            Route::get('password/reset', [PasswordResetController::class, 'showResetForm'])
                ->name('password.reset');
            Route::post('password/reset', [PasswordResetController::class, 'reset'])
                ->middleware('throttle:10,1')
                ->name('password.update');
        });

        Route::get('verification/notice', [CustomerRegistrationController::class, 'notice'])
            ->name('verification.notice');
        Route::get('verification/verify', [CustomerRegistrationController::class, 'verify'])
            ->middleware('throttle:10,1')
            ->name('verification.verify');
        Route::post('verification/resend', [CustomerRegistrationController::class, 'resend'])
            ->middleware('throttle:10,1')
            ->name('verification.resend');

        Route::get('email/change/verify', [CustomerProfileController::class, 'verifyEmailChange'])
            ->middleware('throttle:10,1')
            ->name('email.change.verify');

        Route::post('logout', [AuthenticationController::class, 'logout'])
            ->middleware(['auth', EnsureActiveAuthenticationSession::class, 'role:customer', EnsureCustomerAccount::class])
            ->name('logout');

        Route::prefix('security/devices')
            ->name('security.devices.')
            ->middleware(['auth', EnsureActiveAuthenticationSession::class, 'role:customer', EnsureCustomerAccount::class])
            ->group(function (): void {
                Route::get('/', [DeviceTrustController::class, 'index'])->name('index');
                Route::delete('{device}', [DeviceTrustController::class, 'destroy'])->name('destroy');
                Route::post('{device}/replace', [DeviceTrustController::class, 'replace'])->name('replace');
            });

        Route::prefix('security/password')
            ->name('security.password.')
            ->middleware(['auth', EnsureActiveAuthenticationSession::class, 'role:customer', EnsureCustomerAccount::class])
            ->group(function (): void {
                Route::get('/', [ForcedPasswordChangeController::class, 'edit'])->name('edit');
                Route::put('/', [ForcedPasswordChangeController::class, 'update'])->name('update');
            });

        Route::prefix('password/change-required')
            ->name('password.force-change.')
            ->middleware(['auth', EnsureActiveAuthenticationSession::class, 'role:customer', EnsureCustomerAccount::class])
            ->group(function (): void {
                Route::get('/', [ForcedPasswordChangeController::class, 'edit'])->name('edit');
                Route::put('/', [ForcedPasswordChangeController::class, 'update'])->name('update');
            });
    });

Route::get('/boutique', [ProductStorefrontController::class, 'index'])
    ->name('storefront.products.index');
Route::get('/boutique/produits/{product}', [ProductStorefrontController::class, 'show'])
    ->whereUlid('product')
    ->name('storefront.products.show');
Route::get('/panier', [SalesCartPageController::class, 'show'])
    ->name('sales.cart.page');

Route::prefix('cart')->name('sales.cart.')->group(function (): void {
    Route::get('/', [SalesCartController::class, 'show'])
        ->middleware('throttle:120,1')
        ->name('show');
    Route::post('/items', [SalesCartController::class, 'addItem'])
        ->middleware('throttle:60,1')
        ->name('items.store');
    Route::post('/items/{itemId}/accept-current', [SalesCartController::class, 'acceptCurrentChanges'])
        ->whereUlid('itemId')
        ->middleware('throttle:30,1')
        ->name('items.accept-current');
    Route::patch('/items/{itemId}', [SalesCartController::class, 'updateItem'])
        ->whereUlid('itemId')
        ->middleware('throttle:60,1')
        ->name('items.update');
    Route::delete('/items/{itemId}', [SalesCartController::class, 'removeItem'])
        ->whereUlid('itemId')
        ->middleware('throttle:60,1')
        ->name('items.destroy');
    Route::delete('/', [SalesCartController::class, 'clear'])
        ->middleware('throttle:20,1')
        ->name('clear');

    Route::middleware([
        'auth',
        EnsureActiveAuthenticationSession::class,
        'role:customer',
        EnsureCustomerAccount::class,
    ])->group(function (): void {
        Route::get('/checkout', [SalesCartController::class, 'checkoutPage'])
            ->name('checkout.page');
        Route::post('/checkout/direct', [SalesCartController::class, 'startDirectCheckout'])
            ->middleware('throttle:20,1')
            ->name('checkout.direct.start');
        Route::get('/checkout/direct/{cartId}', [SalesCartController::class, 'directCheckoutPage'])
            ->whereUlid('cartId')
            ->name('checkout.direct');
        Route::get('/checkout/preview', [SalesCartController::class, 'checkoutPreview'])
            ->middleware('throttle:30,1')
            ->name('checkout.preview');
        Route::post('/checkout/confirm', [SalesCartController::class, 'checkoutConfirm'])
            ->middleware('throttle:10,1')
            ->name('checkout.confirm');
    });
});

Route::middleware([
    'auth',
    EnsureActiveAuthenticationSession::class,
    'role:super_admin_root,super_admin,cooperative_admin,user,customer',
])->prefix('orders')->name('orders.')->group(function (): void {
    Route::get('{orderId}/availability', [OrderAvailabilityController::class, 'show'])
        ->whereUlid('orderId')
        ->name('availability.show');
});

Route::prefix('notifications')
    ->name('notifications.')
    ->middleware(['auth', EnsureActiveAuthenticationSession::class, 'role:super_admin_root,super_admin,cooperative_admin,user,customer'])
    ->group(function (): void {
        Route::get('/', [NotificationCenterController::class, 'index'])->name('index');
        Route::post('/read-all', [NotificationCenterController::class, 'markAllRead'])->name('read-all');
        Route::post('/{notificationId}/read', [NotificationCenterController::class, 'markRead'])
            ->whereUuid('notificationId')->name('read');
    });

/*
|--------------------------------------------------------------------------
| Password Reset
|--------------------------------------------------------------------------
*/
Route::prefix('password')
    ->name('password.')
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
    ->middleware(['auth', EnsureActiveAuthenticationSession::class, 'role:super_admin_root,super_admin,cooperative_admin,user'])
    ->group(function (): void {
        Route::get('/', [ForcedPasswordChangeController::class, 'edit'])->name('edit');
        Route::put('/', [ForcedPasswordChangeController::class, 'update'])->name('update');
    });

Route::get('/security/password', [ForcedPasswordChangeController::class, 'edit'])
    ->middleware(['auth', EnsureActiveAuthenticationSession::class, 'role:super_admin_root,super_admin,cooperative_admin,user'])
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

    if ($user->hasRole('customer')) {
        return redirect()->route($user->must_change_password
            ? 'customer.password.force-change.edit'
            : 'customer.dashboard');
    }

    if ($user->must_change_password) {
        return redirect()->route('password.force-change.edit');
    }

    if ($user->hasRole('super_admin_root')) {
        return redirect()->route('root.backoffice.dashboard');
    }

    if ($user->hasRole('user')) {
        return redirect()->route('user.dashboard');
    }

    if ($user->hasAnyRole(['super_admin', 'cooperative_admin'])) {
        return redirect()->route('backoffice.dashboard');
    }

    abort(403);
});

Route::get('/user/dashboard', [UserDashboardController::class, '__invoke'])
    ->middleware(['auth', EnsureActiveAuthenticationSession::class, 'role:user'])
    ->name('user.dashboard');

Route::prefix('customer/orders')
    ->name('customer.orders.')
    ->middleware(['auth', EnsureActiveAuthenticationSession::class, 'role:customer', EnsureCustomerAccount::class])
    ->group(function (): void {
        Route::get('/', [CustomerOrderHistoryController::class, 'index'])->name('index');
        Route::get('/{orderId}', [CustomerOrderHistoryController::class, 'show'])
            ->whereUlid('orderId')
            ->name('show');
    });

Route::get('/customer/dashboard', [CustomerDashboardController::class, '__invoke'])
    ->middleware(['auth', EnsureActiveAuthenticationSession::class, 'role:customer', EnsureCustomerAccount::class])
    ->name('customer.dashboard');

Route::prefix('customer/profile')
    ->name('customer.profile.')
    ->middleware(['auth', EnsureActiveAuthenticationSession::class, 'role:customer', EnsureCustomerAccount::class])
    ->group(function (): void {
        Route::get('/', [CustomerProfileController::class, 'edit'])->name('edit');
        Route::put('/', [CustomerProfileController::class, 'update'])->name('update');
        Route::post('addresses', [CustomerProfileController::class, 'storeAddress'])->name('addresses.store');
        Route::patch('addresses/{address}/primary', [CustomerProfileController::class, 'setPrimaryAddress'])->name('addresses.primary');
        Route::delete('addresses/{address}', [CustomerProfileController::class, 'deleteAddress'])->name('addresses.destroy');
        Route::post('email', [CustomerProfileController::class, 'requestEmailChange'])
            ->middleware('throttle:5,1')
            ->name('email.change');
    });

Route::get('/user/invitation/accept', [UserInvitationController::class, 'show'])
    ->middleware('throttle:10,1')
    ->name('user-invitations.show');

Route::post('/user/invitation/accept', [UserInvitationController::class, 'accept'])
    ->middleware('throttle:5,1')
    ->name('user-invitations.accept');

Route::prefix('root/back-office')
    ->name('root.backoffice.')
    ->middleware(['auth', EnsureActiveAuthenticationSession::class, 'role:super_admin_root'])
    ->group(function (): void {
        Route::get('dashboard', [SuperAdminRootDashboardController::class, '__invoke'])
            ->name('dashboard');

        Route::get('profile/address', [PersonalAddressController::class, 'edit'])->name('profile.address.edit');
        Route::put('profile/address', [PersonalAddressController::class, 'update'])->name('profile.address.update');
        Route::put('users/{user}/address', [PersonalAddressController::class, 'updateUser'])->name('users.address.update');

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

        Route::prefix('security/devices')
            ->name('security.devices.')
            ->group(function (): void {
                Route::get('/', [DeviceTrustController::class, 'index'])->name('index');
                Route::delete('{device}', [DeviceTrustController::class, 'destroy'])->name('destroy');
                Route::post('{device}/replace', [DeviceTrustController::class, 'replace'])->name('replace');
            });

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

        Route::post('users/{user}/api-tokens', [UserApiTokenController::class, 'storeForUser'])
            ->name('users.api-tokens.store');
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
        Route::post('users/{user}/send-password-reset', [SuperAdminRootUserManagementController::class, 'sendPasswordReset'])
            ->name('users.send-password-reset');

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
        Route::patch('cooperatives/{cooperative}/restore-deleted', [SuperAdminRootCooperativeController::class, 'restoreDeleted'])
            ->name('cooperatives.restore-deleted');

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

                Route::get('catalogs/{catalog}/pdf', [SuperAdminRootCatalogController::class, 'downloadPdf'])
                    ->name('catalogs.pdf');

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

                Route::patch(
                    'products/{product}/restore',
                    [SuperAdminRootProductController::class, 'restore'],
                )->name('products.restore');

                Route::prefix('products/{product}/variants')
                    ->name('products.variants.')
                    ->group(function (): void {
                        Route::get('create', [SuperAdminRootProductVariantController::class, 'create'])
                            ->whereUlid('product')->name('create');
                        Route::post('/', [SuperAdminRootProductVariantController::class, 'store'])
                            ->whereUlid('product')->name('store');
                        Route::get('{variant}/edit', [SuperAdminRootProductVariantController::class, 'edit'])
                            ->whereUlid('product')->whereUlid('variant')->name('edit');
                        Route::put('{variant}', [SuperAdminRootProductVariantController::class, 'update'])
                            ->whereUlid('product')->whereUlid('variant')->name('update');
                        Route::patch('{variant}/activate', [SuperAdminRootProductVariantController::class, 'activate'])
                            ->whereUlid('product')->whereUlid('variant')->name('activate');
                        Route::patch('{variant}/deactivate', [SuperAdminRootProductVariantController::class, 'deactivate'])
                            ->whereUlid('product')->whereUlid('variant')->name('deactivate');
                        Route::patch('{variant}/restore', [SuperAdminRootProductVariantController::class, 'restore'])
                            ->whereUlid('product')->whereUlid('variant')->name('restore');
                        Route::delete('{variant}', [SuperAdminRootProductVariantController::class, 'destroy'])
                            ->whereUlid('product')->whereUlid('variant')->name('destroy');
                    });
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
    ->middleware(['auth', EnsureActiveAuthenticationSession::class, 'role:super_admin,cooperative_admin,user'])
    ->group(function (): void {
        Route::get('profile/address', [PersonalAddressController::class, 'edit'])->name('profile.address.edit');
        Route::put('profile/address', [PersonalAddressController::class, 'update'])->name('profile.address.update');
        Route::put('users/{user}/address', [PersonalAddressController::class, 'updateUser'])->name('users.address.update');

        Route::get('security/sessions', [AuthenticationSessionController::class, 'index'])
            ->name('security.sessions.index');
        Route::post('security/sessions/extend', [AuthenticationSessionController::class, 'extend'])
            ->name('security.sessions.extend');
        Route::post('security/sessions/logout-everywhere', [AuthenticationController::class, 'logoutEverywhere'])
            ->name('security.sessions.logout-everywhere');
        Route::delete('security/sessions/{session}', [AuthenticationSessionController::class, 'revokeOwn'])
            ->name('security.sessions.destroy');

        Route::prefix('security/devices')
            ->name('security.devices.')
            ->group(function (): void {
                Route::get('/', [DeviceTrustController::class, 'index'])->name('index');
                Route::delete('{device}', [DeviceTrustController::class, 'destroy'])->name('destroy');
                Route::post('{device}/replace', [DeviceTrustController::class, 'replace'])->name('replace');
            });

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
    ->middleware(['auth', EnsureActiveAuthenticationSession::class, 'role:super_admin,cooperative_admin'])
    ->group(function (): void {

        Route::get('dashboard', [BackOfficeDashboardController::class, '__invoke'])
            ->name('dashboard');

        Route::get('users', [UserManagementController::class, 'index'])
            ->name('users.index');

        Route::get('users/create', [UserManagementController::class, 'create'])
            ->name('users.create');

        Route::post('users', [UserManagementController::class, 'store'])
            ->name('users.store');

        Route::get('users/{user}/edit', [UserManagementController::class, 'edit'])
            ->name('users.edit');

        Route::put('users/{user}', [UserManagementController::class, 'update'])
            ->name('users.update');

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
        Route::post('users/{user}/send-password-reset', [UserManagementController::class, 'sendPasswordReset'])
            ->name('users.send-password-reset');

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

                Route::get('catalogs/{catalog}/pdf', [CatalogController::class, 'downloadPdf'])
                    ->name('catalogs.pdf');

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

                Route::patch(
                    'products/{product}/restore',
                    [ProductController::class, 'restore'],
                )->name('products.restore');
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

Route::prefix('root/back-office/orders/cooperative')
    ->name('root.backoffice.orders.cooperative.')
    ->middleware(['auth', EnsureActiveAuthenticationSession::class, 'role:super_admin_root'])
    ->group(function (): void {
        Route::get('/', [CooperativeOrderController::class, 'index'])->name('index');
        Route::get('/{cooperativeOrderId}', [CooperativeOrderController::class, 'show'])->whereUlid('cooperativeOrderId')->name('show');
        Route::post('/{cooperativeOrderId}/confirm', [CooperativeOrderController::class, 'confirm'])->whereUlid('cooperativeOrderId')->name('confirm');
        Route::post('/{cooperativeOrderId}/refuse', [CooperativeOrderController::class, 'refuse'])->whereUlid('cooperativeOrderId')->name('refuse');
        Route::post('/{cooperativeOrderId}/administrative-decision', [CooperativeOrderController::class, 'administrativeDecision'])->whereUlid('cooperativeOrderId')->name('administrative-decision');
        Route::post('/{cooperativeOrderId}/extend-deadline', [CooperativeOrderController::class, 'extendDeadline'])->whereUlid('cooperativeOrderId')->name('extend-deadline');
    });

Route::prefix('back-office/orders/cooperative')
    ->name('backoffice.orders.cooperative.')
    ->middleware(['auth', EnsureActiveAuthenticationSession::class, 'role:super_admin,cooperative_admin'])
    ->group(function (): void {
        Route::get('/', [CooperativeOrderController::class, 'index'])->name('index');
        Route::get('/{cooperativeOrderId}', [CooperativeOrderController::class, 'show'])->whereUlid('cooperativeOrderId')->name('show');
        Route::post('/{cooperativeOrderId}/confirm', [CooperativeOrderController::class, 'confirm'])->whereUlid('cooperativeOrderId')->name('confirm');
        Route::post('/{cooperativeOrderId}/refuse', [CooperativeOrderController::class, 'refuse'])->whereUlid('cooperativeOrderId')->name('refuse');
    });

Route::prefix('back-office/orders/cooperative')
    ->name('backoffice.orders.cooperative.')
    ->middleware(['auth', EnsureActiveAuthenticationSession::class, 'role:super_admin'])
    ->group(function (): void {
        Route::post('/{cooperativeOrderId}/administrative-decision', [CooperativeOrderController::class, 'administrativeDecision'])->whereUlid('cooperativeOrderId')->name('administrative-decision');
        Route::post('/{cooperativeOrderId}/extend-deadline', [CooperativeOrderController::class, 'extendDeadline'])->whereUlid('cooperativeOrderId')->name('extend-deadline');
    });

Route::post('/orders/safety-holds/{holdId}/resolve', [OrderSafetyHoldController::class, 'resolve'])
    ->middleware([
        'auth',
        EnsureActiveAuthenticationSession::class,
        'role:super_admin_root,super_admin,cooperative_admin',
    ])
    ->whereUlid('holdId')
    ->name('orders.safety-holds.resolve');

/*
|--------------------------------------------------------------------------
| Inventory PDF reports
|--------------------------------------------------------------------------
*/

Route::get('/cooperatives/{cooperativeId}/inventory/report.pdf', [InventoryReportController::class, '__invoke'])
    ->middleware([
        'auth',
        EnsureActiveAuthenticationSession::class,
        'role:super_admin_root,super_admin,cooperative_admin,user',
    ])
    ->whereUlid('cooperativeId')
    ->name('inventory.reports.pdf');

Route::get('/inventory/security-reports', [InventorySecurityReportController::class, 'index'])
    ->middleware([
        'auth',
        EnsureActiveAuthenticationSession::class,
        'role:super_admin_root,super_admin,cooperative_admin,user',
    ])
    ->name('inventory.security-reports.index');

Route::prefix('cooperatives/{cooperativeId}/inventory/security-reports')
    ->name('inventory.security-reports.')
    ->middleware([
        'auth',
        EnsureActiveAuthenticationSession::class,
        'role:super_admin_root,super_admin,cooperative_admin,user',
    ])
    ->where(['cooperativeId' => '[0-9A-HJKMNP-TV-Z]{26}'])
    ->group(function (): void {
        Route::post('/', [InventorySecurityReportController::class, 'store'])->name('store');
    });

Route::prefix('inventory/security-reports/{reportId}')
    ->name('inventory.security-reports.')
    ->middleware([
        'auth',
        EnsureActiveAuthenticationSession::class,
        'role:super_admin_root,super_admin,cooperative_admin,user',
    ])
    ->where(['reportId' => '[0-9A-HJKMNP-TV-Z]{26}'])
    ->group(function (): void {
        Route::get('/', [InventorySecurityReportController::class, 'show'])->name('show');
        Route::patch('/close', [InventorySecurityReportController::class, 'close'])->name('close');
        Route::patch('/reopen', [InventorySecurityReportController::class, 'reopen'])->name('reopen');
    });

/*
|--------------------------------------------------------------------------
| Media
|--------------------------------------------------------------------------
*/

// Public product and cooperative images may be streamed without exposing
// their physical storage location. Private media requires an authenticated
// owner/administrator and is authorized by MediaPolicy.
Route::get('/media/{media}', [MediaController::class, 'show'])
    ->middleware(['media.enumeration', 'throttle:media-public'])
    ->whereUlid('media')
    ->name('media.show');

Route::get('/media/{media}/{variant}', [MediaController::class, 'show'])
    ->middleware(['media.enumeration', 'throttle:media-public'])
    ->whereUlid('media')
    ->whereIn('variant', ['original', 'thumbnail', 'small', 'medium', 'large'])
    ->name('media.variant');

Route::middleware(['auth', EnsureActiveAuthenticationSession::class])
    ->prefix('media')
    ->name('media.')
    ->group(function (): void {
        Route::prefix('incidents')
            ->name('incidents.')
            ->middleware(['media.root-audit', 'role:super_admin_root'])
            ->group(function (): void {
                Route::get('/', [MediaIncidentController::class, 'index'])->name('index');
                Route::post('/', [MediaIncidentController::class, 'store'])->name('store');
                Route::get('/export', [MediaIncidentController::class, 'export'])->name('export');
                Route::get('/{incident}', [MediaIncidentController::class, 'show'])
                    ->whereUlid('incident')->name('show');
                Route::patch('/{incident}/status', [MediaIncidentController::class, 'transition'])
                    ->whereUlid('incident')->name('status');
                Route::post('/{incident}/reopen', [MediaIncidentController::class, 'reopen'])
                    ->whereUlid('incident')->name('reopen');
                Route::post('/{incident}/attachments', [MediaIncidentController::class, 'attach'])
                    ->whereUlid('incident')->name('attachments.store');
            });
        Route::get('{media}/private', [MediaController::class, 'showPrivate'])
            ->middleware('throttle:media-public')
            ->whereUlid('media')
            ->name('private');
        Route::post('/', [MediaController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('store');
        Route::put('{media}/primary', [MediaController::class, 'primary'])
            ->whereUlid('media')
            ->name('primary');
        Route::patch('{media}/order', [MediaController::class, 'reorder'])
            ->whereUlid('media')
            ->name('reorder');
        Route::get('owners/{ownerType}/{ownerId}/history', [MediaController::class, 'ownerHistory'])
            ->whereIn('ownerType', ['product', 'cooperative', 'user', 'incident'])
            ->whereUlid('ownerId')
            ->middleware(['media.root-audit', 'role:super_admin_root'])
            ->name('owners.history');
        Route::get('{media}/replacements', [MediaController::class, 'replacementHistory'])
            ->whereUlid('media')
            ->name('replacements');
        Route::post('{media}/restore', [MediaController::class, 'restore'])
            ->whereUlid('media')
            ->name('restore');
        Route::delete('{media}', [MediaController::class, 'destroy'])
            ->whereUlid('media')
            ->name('destroy');
    });

Route::middleware([
    'auth',
    EnsureActiveAuthenticationSession::class,
    'role:super_admin_root,super_admin,cooperative_admin,user',
])->prefix('backoffice/inventory')->name('backoffice.inventory.')->group(function (): void {
    Route::get('/', [InventoryWorkspaceController::class, '__invoke'])->name('index');
    Route::get('/stocks', [InventoryStockController::class, 'index'])
        ->name('stocks.index.all');
    Route::get('/cooperatives/{cooperativeId}/stocks', [InventoryStockController::class, 'index'])
        ->whereUlid('cooperativeId')
        ->name('stocks.index');
    Route::get('/stocks/{stockId}', [InventoryStockController::class, 'show'])
        ->whereUlid('stockId')
        ->name('stocks.show');
    Route::post('/cooperatives/{cooperativeId}/stocks/initialize', [InventoryStockController::class, 'initialize'])
        ->whereUlid('cooperativeId')
        ->name('stocks.initialize');
    Route::post('/cooperatives/{cooperativeId}/stocks/receive', [InventoryStockController::class, 'receive'])
        ->whereUlid('cooperativeId')
        ->name('stocks.receive');
    Route::post('/cooperatives/{cooperativeId}/stocks/adjust', [InventoryStockController::class, 'adjust'])
        ->whereUlid('cooperativeId')
        ->name('stocks.adjust');

    Route::post('/stocks/{stockId}/threshold', [InventoryThresholdController::class, 'update'])
        ->whereUlid('stockId')
        ->name('stocks.threshold.update');

    Route::get('/count-sessions/{sessionId}', [InventoryCountController::class, 'show'])
        ->whereUlid('sessionId')
        ->name('count-sessions.show');
    Route::get('/count-sessions/{sessionId}/workspace', [InventoryCountWorkspaceController::class, '__invoke'])
        ->whereUlid('sessionId')
        ->name('count-sessions.workspace');
    Route::get('/cooperatives/{cooperativeId}/count-sessions', [InventoryCountController::class, 'index'])
        ->whereUlid('cooperativeId')
        ->name('count-sessions.index');
    Route::post('/cooperatives/{cooperativeId}/count-sessions', [InventoryCountController::class, 'create'])
        ->whereUlid('cooperativeId')
        ->name('count-sessions.create');
    Route::post('/count-sessions/{sessionId}/items/{itemId}/count', [InventoryCountController::class, 'record'])
        ->whereUlid('sessionId')
        ->whereUlid('itemId')
        ->name('count-sessions.items.count');
    Route::post('/count-sessions/{sessionId}/validate', [InventoryCountController::class, 'validateSession'])
        ->whereUlid('sessionId')
        ->name('count-sessions.validate');
    Route::post('/count-sessions/{sessionId}/cancel', [InventoryCountController::class, 'cancel'])
        ->whereUlid('sessionId')
        ->name('count-sessions.cancel');

    Route::get('/cooperatives/{cooperativeId}/quarantines', [InventoryQuarantineController::class, 'index'])
        ->whereUlid('cooperativeId')
        ->name('quarantines.index');
    Route::post('/cooperatives/{cooperativeId}/quarantines/receive', [InventoryQuarantineController::class, 'receive'])
        ->whereUlid('cooperativeId')
        ->name('quarantines.receive');
    Route::post('/quarantines/{quarantineId}/inspect', [InventoryQuarantineController::class, 'inspect'])
        ->whereUlid('quarantineId')
        ->name('quarantines.inspect');
    Route::post('/quarantines/{quarantineId}/authorize-sale', [InventoryQuarantineController::class, 'authorizeSale'])
        ->whereUlid('quarantineId')
        ->name('quarantines.authorize-sale');
    Route::post('/quarantine-authorizations/{authorizationId}/revoke', [InventoryQuarantineController::class, 'revokeSale'])
        ->whereUlid('authorizationId')
        ->name('quarantine-authorizations.revoke');
});
