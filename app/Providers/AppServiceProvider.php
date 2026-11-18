<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authentication\Models\PersonalAccessToken;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Media\Observers\MediaOwnerObserver;
use App\Domains\Products\Models\Product;
use App\Domains\Identity\Authentication\Workflows\AuthenticationWorkflowHook;
use App\Domains\Identity\Authentication\Workflows\RecordNewDeviceAuthenticationWorkflowHook;
use App\Domains\Identity\UserManagement\Contracts\SuperAdminCooperativeAssignmentService;
use App\Domains\Identity\UserManagement\Services\UserManagementService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            SuperAdminCooperativeAssignmentService::class,
            UserManagementService::class,
        );

        $this->app->bind(
            AuthenticationWorkflowHook::class,
            RecordNewDeviceAuthenticationWorkflowHook::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        Product::observe(MediaOwnerObserver::class);
        Cooperative::observe(MediaOwnerObserver::class);
        User::observe(MediaOwnerObserver::class);

        RateLimiter::for('media-public', static fn (Request $request) => Limit::perMinute(60)
            ->by('media-public:'.($request->ip() ?? 'unknown')));

    }
}
