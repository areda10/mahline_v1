<?php

return [
    App\Providers\AppServiceProvider::class,

    /*
    |--------------------------------------------------------------------------
    | MAHLINE Framework
    |--------------------------------------------------------------------------
    */

    App\Core\Database\Providers\DatabaseMacroServiceProvider::class,
    App\Domains\Identity\Authorization\Providers\AuthorizationServiceProvider::class,
];