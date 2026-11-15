<?php

use App\Core\Database\Providers\DatabaseMacroServiceProvider;
use App\Domains\Identity\Authorization\Providers\AuthorizationServiceProvider;
use App\Domains\Identity\Authorization\Providers\PermissionGateServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,

    /*
    |--------------------------------------------------------------------------
    | MAHLINE Framework
    |--------------------------------------------------------------------------
    */

    DatabaseMacroServiceProvider::class,
    AuthorizationServiceProvider::class,
    PermissionGateServiceProvider::class,
];
