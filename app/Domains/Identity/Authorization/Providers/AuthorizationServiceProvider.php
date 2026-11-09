<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authorization\Providers;

use App\Domains\Audit\Models\AuditEvent;
use App\Domains\Audit\Policies\AuditEventPolicy;
use App\Domains\Catalog\Models\Catalog;
use App\Domains\Catalog\Policies\CatalogPolicy;
use App\Domains\Categories\Models\Category;
use App\Domains\Categories\Policies\CategoryPolicy;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authorization\Policies\CooperativePolicy;
use App\Domains\Identity\Authorization\Policies\UserPolicy;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Products\Models\Product;
use App\Domains\Products\Policies\ProductPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class AuthorizationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(AuditEvent::class, AuditEventPolicy::class);

        Gate::policy(
            Catalog::class,
            CatalogPolicy::class,
        );
        
        Gate::policy(
            Category::class,
            CategoryPolicy::class,
        );

        Gate::policy(
            Cooperative::class,
            CooperativePolicy::class,
        );

        Gate::policy(
            Product::class,
            ProductPolicy::class,
        );

        Gate::policy(
            User::class,
            UserPolicy::class,
        );

    }
}