<?php

declare(strict_types=1);

namespace App\Domains\Categories\Providers;

use App\Domains\Categories\Models\Category;
use App\Domains\Categories\Policies\CategoryPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class CategoriesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Category::class, CategoryPolicy::class);
    }
}
