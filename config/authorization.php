<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | MAHLINE Role Permission Matrix
    |--------------------------------------------------------------------------
    |
    | Each role receives only the permissions explicitly declared here.
    |
    | Permission slugs must exist in the permissions table.
    |
    */

    'role_permissions' => [

        /*
         * Global MAHLINE platform administration.
         */
        'super_admin_root' => [
            'catalogs.view',
            'catalogs.create',
            'catalogs.update',
            'catalogs.delete',
            'catalogs.restore',
            'catalogs.force_delete',

            'categories.view',
            'categories.create',
            'categories.update',
            'categories.delete',
            'categories.restore',
            'categories.force_delete',

            'products.view',
            'products.create',
            'products.update',
            'products.delete',
            'products.restore',
            'products.force_delete',
        ],

        /*
         * Administration of assigned cooperatives.
         */
        'super_admin' => [
            'catalogs.view',
            'catalogs.create',
            'catalogs.update',
            'catalogs.delete',
            'catalogs.restore',

            'categories.view',
            'categories.create',
            'categories.update',
            'categories.delete',
            'categories.restore',

            'products.view',
            'products.create',
            'products.update',
            'products.delete',
            'products.restore',
        ],

        /*
         * Administration of the user's own cooperative.
         */
        'cooperative_admin' => [
            'catalogs.view',
            'catalogs.create',
            'catalogs.update',
            'catalogs.delete',
            'catalogs.restore',

            'categories.view',
            'categories.create',
            'categories.update',
            'categories.delete',
            'categories.restore',

            'products.view',
            'products.create',
            'products.update',
            'products.delete',
            'products.restore',
        ],

        /*
         * Standard cooperative user.
         */
        'user' => [
            'catalogs.view',
            'categories.view',
            'products.view',
        ],

    ],

];