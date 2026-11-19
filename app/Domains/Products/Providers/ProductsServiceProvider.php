<?php

declare(strict_types=1);

namespace App\Domains\Products\Providers;

use App\Domains\Products\Contracts\CheckoutCatalog;
use App\Domains\Products\Models\Product;
use App\Domains\Products\Services\ProductService;
use App\Domains\Products\Policies\ProductPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class ProductsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CheckoutCatalog::class, ProductService::class);
    }

    public function boot(): void
    {
        Gate::policy(Product::class, ProductPolicy::class);
    }
}
