<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Providers;

use App\Domains\Inventory\Contracts\InventoryReservationManager;
use App\Domains\Inventory\Services\Reservations\InventoryReservationService;
use Illuminate\Support\ServiceProvider;

final class InventoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(InventoryReservationManager::class, InventoryReservationService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
    }
}
