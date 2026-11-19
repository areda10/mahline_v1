<?php

declare(strict_types=1);

namespace App\Domains\Cooperatives\Providers;

use App\Domains\Cooperatives\Contracts\CooperativeStatusReader;
use App\Domains\Cooperatives\Contracts\CooperativeIdentityReference;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Cooperatives\Services\CooperativeStatusReaderService;
use App\Domains\Cooperatives\Services\CooperativeIdentityReferenceService;
use App\Domains\Cooperatives\Policies\CooperativePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class CooperativesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CooperativeStatusReader::class, CooperativeStatusReaderService::class);
        $this->app->bind(CooperativeIdentityReference::class, CooperativeIdentityReferenceService::class);
    }

    public function boot(): void
    {
        Gate::policy(Cooperative::class, CooperativePolicy::class);
    }
}
