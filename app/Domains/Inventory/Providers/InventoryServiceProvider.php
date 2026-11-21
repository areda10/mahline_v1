<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Providers;

use App\Domains\Inventory\Console\Commands\EscalateInventorySecurityReports;
use App\Domains\Inventory\Console\Commands\ExpireInventoryReservations;
use App\Domains\Inventory\Console\Commands\PublishInventoryOutbox;
use App\Domains\Inventory\Console\Commands\ReconcileInventoryAlerts;
use App\Domains\Inventory\Contracts\InventoryCheckoutAvailabilityReader;
use App\Domains\Inventory\Contracts\InventoryCountManager;
use App\Domains\Inventory\Contracts\InventoryOrderAvailabilityReader;
use App\Domains\Inventory\Contracts\InventoryQuarantineManager;
use App\Domains\Inventory\Contracts\InventoryReservationManager;
use App\Domains\Inventory\Contracts\InventorySecurityReportManager;
use App\Domains\Inventory\Contracts\InventoryStockManager;
use App\Domains\Inventory\Contracts\InventoryThresholdManager;
use App\Domains\Inventory\Services\Alerts\InventoryThresholdService;
use App\Domains\Inventory\Services\Counts\InventoryCountService;
use App\Domains\Inventory\Services\Quarantine\InventoryQuarantineService;
use App\Domains\Inventory\Services\Reservations\InventoryCheckoutAvailabilityService;
use App\Domains\Inventory\Services\Reservations\InventoryOrderAvailabilityService;
use App\Domains\Inventory\Services\Reservations\InventoryReservationService;
use App\Domains\Inventory\Services\Security\InventorySecurityReportService;
use App\Domains\Inventory\Services\Stock\InventoryStockService;
use App\Domains\Inventory\Services\Stock\InventoryStockUnitChangeGuardService;
use App\Shared\Contracts\StockUnitChangeGuard;
use Illuminate\Support\ServiceProvider;

final class InventoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(InventoryReservationManager::class, InventoryReservationService::class);
        $this->app->bind(InventoryOrderAvailabilityReader::class, InventoryOrderAvailabilityService::class);
        $this->app->bind(InventoryCheckoutAvailabilityReader::class, InventoryCheckoutAvailabilityService::class);
        $this->app->bind(InventoryStockManager::class, InventoryStockService::class);
        $this->app->bind(StockUnitChangeGuard::class, InventoryStockUnitChangeGuardService::class);
        $this->app->bind(InventoryQuarantineManager::class, InventoryQuarantineService::class);
        $this->app->bind(InventoryCountManager::class, InventoryCountService::class);
        $this->app->bind(InventoryThresholdManager::class, InventoryThresholdService::class);
        $this->app->bind(InventorySecurityReportManager::class, InventorySecurityReportService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->commands([
            ExpireInventoryReservations::class,
            PublishInventoryOutbox::class,
            ReconcileInventoryAlerts::class,
            EscalateInventorySecurityReports::class,
        ]);
    }
}
