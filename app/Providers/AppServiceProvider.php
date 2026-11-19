<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Media\Observers\MediaOwnerObserver;
use App\Domains\Products\Models\Product;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Product::observe(MediaOwnerObserver::class);
        Cooperative::observe(MediaOwnerObserver::class);
        User::observe(MediaOwnerObserver::class);

        RateLimiter::for('media-public', static fn (Request $request) => Limit::perMinute(60)
            ->by('media-public:'.($request->ip() ?? 'unknown')));

    }
}
