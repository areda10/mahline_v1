<?php

use App\Core\Database\Providers\DatabaseMacroServiceProvider;
use App\Domains\Audit\Providers\AuditServiceProvider;
use App\Domains\Catalog\Providers\CatalogServiceProvider;
use App\Domains\Categories\Providers\CategoriesServiceProvider;
use App\Domains\Cooperatives\Providers\CooperativesServiceProvider;
use App\Domains\Identity\Authorization\Providers\AuthorizationServiceProvider;
use App\Domains\Identity\Authorization\Providers\PermissionGateServiceProvider;
use App\Domains\Localization\Providers\LocalizationServiceProvider;
use App\Domains\Media\Providers\MediaServiceProvider;
use App\Domains\Products\Providers\ProductsServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,

    /*
    |--------------------------------------------------------------------------
    | MAHLINE Framework
    |--------------------------------------------------------------------------
    */

    DatabaseMacroServiceProvider::class,
    AuditServiceProvider::class,
    CatalogServiceProvider::class,
    CategoriesServiceProvider::class,
    MediaServiceProvider::class,
    ProductsServiceProvider::class,
    CooperativesServiceProvider::class,
    LocalizationServiceProvider::class,
    AuthorizationServiceProvider::class,
    PermissionGateServiceProvider::class,
];
