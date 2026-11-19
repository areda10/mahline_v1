<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Providers;

use App\Domains\Catalog\Models\Catalog;
use App\Domains\Catalog\Policies\CatalogPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class CatalogServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Catalog::class, CatalogPolicy::class);
    }
}
