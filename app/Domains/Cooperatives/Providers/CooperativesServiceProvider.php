<?php

declare(strict_types=1);

namespace App\Domains\Cooperatives\Providers;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Cooperatives\Policies\CooperativePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class CooperativesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Cooperative::class, CooperativePolicy::class);
    }
}
