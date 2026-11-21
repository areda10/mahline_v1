<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Providers;

use App\Domains\Inventory\Contracts\InventoryCheckoutAvailabilityReader;
use App\Domains\Inventory\Contracts\InventoryReservationManager;
use App\Domains\Inventory\Contracts\InventoryStockManager;
use App\Domains\Inventory\Services\Reservations\InventoryCheckoutAvailabilityService;
use App\Domains\Inventory\Services\Reservations\InventoryReservationService;
use App\Domains\Inventory\Services\Stock\InventoryStockService;
use Illuminate\Support\ServiceProvider;

final class InventoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(InventoryReservationManager::class, InventoryReservationService::class);
        $this->app->bind(InventoryCheckoutAvailabilityReader::class, InventoryCheckoutAvailabilityService::class);
        $this->app->bind(InventoryStockManager::class, InventoryStockService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
    }
}
