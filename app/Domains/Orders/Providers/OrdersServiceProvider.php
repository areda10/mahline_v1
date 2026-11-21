<?php

declare(strict_types=1);

namespace App\Domains\Orders\Providers;

use App\Domains\Inventory\Events\InventoryDomainEvent;
use App\Domains\Orders\Listeners\HandleInventoryDomainEvent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class OrdersServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        Event::listen(InventoryDomainEvent::class, HandleInventoryDomainEvent::class);
    }
}
