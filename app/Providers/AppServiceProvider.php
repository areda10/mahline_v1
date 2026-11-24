<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Media\Contracts\MediaVideoInspector;
use App\Domains\Media\Observers\MediaOwnerObserver;
use App\Domains\Media\Services\FfprobeMediaVideoInspector;
use App\Domains\Products\Models\Product;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(MediaVideoInspector::class, FfprobeMediaVideoInspector::class);
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
