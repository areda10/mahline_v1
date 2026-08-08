<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Primary Key
    |--------------------------------------------------------------------------
    |
    | MAHLINE uses ULID as the default primary key strategy.
    |
    */

    'primary_key' => [
        'type' => 'ulid',
        'column' => 'id',
    ],

    /*
    |--------------------------------------------------------------------------
    | Foreign Keys
    |--------------------------------------------------------------------------
    |
    | Default conventions used by ForeignKeyMacros.
    |
    */

    'foreign_keys' => [

        'reference_column' => 'id',

        'on_update' => 'cascade',

        'on_delete' => 'restrict',

        'name_suffix' => '_foreign',
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit
    |--------------------------------------------------------------------------
    |
    | Standard audit column names used throughout MAHLINE.
    |
    */

    'audit' => [

        'created_by' => 'created_by',

        'updated_by' => 'updated_by',

        'deleted_by' => 'deleted_by',

        'user_table' => 'users',

        'user_primary_key' => 'id',
    ],

    /*
    |--------------------------------------------------------------------------
    | Soft Deletes
    |--------------------------------------------------------------------------
    |
    | Standard soft-delete column.
    |
    */

    'soft_deletes' => [

        'column' => 'deleted_at',

        'precision' => 0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Timestamps
    |--------------------------------------------------------------------------
    |
    | Standard timestamp configuration.
    |
    */

    'timestamps' => [

        'created_at' => 'created_at',

        'updated_at' => 'updated_at',

        'precision' => 0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Common Columns
    |--------------------------------------------------------------------------
    |
    | Default definitions for reusable MAHLINE column macros.
    |
    */

    'columns' => [

        'code' => [
            'length' => 50,
        ],

        'slug' => [
            'length' => 255,
        ],

        'phone' => [
            'length' => 30,
        ],

        'currency' => [
            'length' => 3,
        ],

        'country' => [
            'length' => 2,
        ],

        'locale' => [
            'length' => 10,
        ],

        'timezone' => [
            'length' => 64,
        ],

        'status' => [
            'length' => 50,
            'default' => 'active',
        ],
    ],

];
