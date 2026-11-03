<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | MAHLINE Role Permission Matrix
    |--------------------------------------------------------------------------
    |
    | Each role receives only the permissions explicitly declared here.
    | Cooperative scope and status-transition rules remain enforced by policies
    | and the UserManagement domain service; permissions alone are not enough.
    |
    */

    'role_permissions' => [

        /*
         * Global MAHLINE platform administration.
         */
        'super_admin_root' => [
            'audit.view',

            'cooperatives.view',
            'cooperatives.create',
            'cooperatives.update',
            'cooperatives.delete',

            'users.view',
            'users.create',
            'users.activate',
            'users.deactivate',
            'users.suspend',
            'users.archive',
            'users.restore',
            'users.delete',
            'users.restore_deleted',
            'users.assign_super_admin',
            'users.unassign_super_admin',

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
            'audit.view',

            'cooperatives.view',
            'cooperatives.create',
            'cooperatives.update',
            'cooperatives.delete',

            'users.view',
            'users.create',
            'users.activate',
            'users.deactivate',
            'users.suspend',

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
         * Cooperative administrators may only consult users in their
         * own cooperative. They cannot create or manage user accounts.
         */
        'cooperative_admin' => [
            'audit.view',
            'cooperatives.view',
            'cooperatives.update',

            'users.view',

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
            'cooperatives.view',
            'catalogs.view',
            'categories.view',
            'products.view',
        ],

    ],

];
