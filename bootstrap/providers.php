<?php

use App\Core\Database\Providers\DatabaseMacroServiceProvider;
use App\Domains\Audit\Providers\AuditServiceProvider;
use App\Domains\Catalog\Providers\CatalogServiceProvider;
use App\Domains\Categories\Providers\CategoriesServiceProvider;
use App\Domains\Cooperatives\Providers\CooperativesServiceProvider;
use App\Domains\Delivery\Providers\DeliveryServiceProvider;
use App\Domains\Identity\Authorization\Providers\AuthorizationServiceProvider;
use App\Domains\Identity\Authorization\Providers\PermissionGateServiceProvider;
use App\Domains\Identity\Providers\IdentityServiceProvider;
use App\Domains\Inventory\Providers\InventoryServiceProvider;
use App\Domains\Localization\Providers\LocalizationServiceProvider;
use App\Domains\Media\Providers\MediaServiceProvider;
use App\Domains\Orders\Providers\OrdersServiceProvider;
use App\Domains\Products\Providers\ProductsServiceProvider;
use App\Domains\Sales\Providers\SalesServiceProvider;
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
    DeliveryServiceProvider::class,
    MediaServiceProvider::class,
    ProductsServiceProvider::class,
    SalesServiceProvider::class,
    InventoryServiceProvider::class,
    OrdersServiceProvider::class,
    CooperativesServiceProvider::class,
    DeliveryServiceProvider::class,
    LocalizationServiceProvider::class,
    IdentityServiceProvider::class,
    AuthorizationServiceProvider::class,
    PermissionGateServiceProvider::class,
];
