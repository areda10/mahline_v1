<?php

declare(strict_types=1);

namespace App\Domains\Orders\Providers;

use App\Domains\Inventory\Events\InventoryDomainEvent;
use App\Shared\Contracts\OrderSafetyHoldManager;
use App\Domains\Orders\Listeners\HandleInventoryDomainEvent;
use App\Domains\Orders\Services\OrderSafetyHoldService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class OrdersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OrderSafetyHoldManager::class, OrderSafetyHoldService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        Event::listen(InventoryDomainEvent::class, HandleInventoryDomainEvent::class);
    }
}
