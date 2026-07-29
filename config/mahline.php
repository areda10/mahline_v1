<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Application
    |--------------------------------------------------------------------------
    */

    'name' => env('APP_NAME', 'MAHLINE'),

    'version' => '1.0.0',

    'architecture' => 'V7.0',

    'framework' => 'MAHLINE Framework',

    'framework_version' => '1.0.0',

    'environment' => env('APP_ENV', 'local'),

    'debug' => env('APP_DEBUG', true),

    /*
    |--------------------------------------------------------------------------
    | Localization
    |--------------------------------------------------------------------------
    */

    'locale' => env('APP_LOCALE', 'fr'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'fr'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'fr_FR'),

    'timezone' => env('APP_TIMEZONE', 'Africa/Casablanca'),

    'currency' => 'MAD',

    'country' => 'MA',

    /*
    |--------------------------------------------------------------------------
    | Marketplace
    |--------------------------------------------------------------------------
    */

    'marketplace' => [

        'multi_cooperative' => true,

        'inventory_management' => true,

        'reviews' => true,

        'favorites' => true,

        'coupons' => true,

        'shipping' => true,

        'invoices' => true,

    ],

    /*
    |--------------------------------------------------------------------------
    | Modules
    |--------------------------------------------------------------------------
    */

    'modules' => [

        'Auth' => true,
        'Users' => true,
        'Roles' => true,
        'Permissions' => true,
        'Cooperatives' => true,
        'Members' => true,
        'Categories' => true,
        'Products' => true,
        'Inventory' => true,
        'Cart' => true,
        'Orders' => true,
        'Payments' => true,
        'Shipping' => true,
        'Addresses' => true,
        'Invoices' => true,
        'Coupons' => true,
        'Reviews' => true,
        'Favorites' => true,
        'Notifications' => true,
        'Messaging' => true,
        'Statistics' => true,
        'Dashboard' => true,
        'Reports' => true,
        'CMS' => true,
        'Settings' => true,
        'Media' => true,
        'Search' => true,
        'Audit' => true,
        'Logs' => true,

    ],

];
