<?php

use App\Core\Database\Providers\DatabaseMacroServiceProvider;
use App\Domains\Cooperatives\Providers\CooperativesServiceProvider;
use App\Domains\Identity\Authorization\Providers\AuthorizationServiceProvider;
use App\Domains\Localization\Providers\LocalizationServiceProvider;
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
    CooperativesServiceProvider::class,
    LocalizationServiceProvider::class,
    AuthorizationServiceProvider::class,
    PermissionGateServiceProvider::class,
];
