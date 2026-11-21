<?php

declare(strict_types=1);

namespace App\Domains\Sales\Providers;

use App\Domains\Sales\Contracts\SalesCartManager;
use App\Domains\Sales\Services\SalesCartService;
use Illuminate\Support\ServiceProvider;

final class SalesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SalesCartManager::class, SalesCartService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
    }
}
