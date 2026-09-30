<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Identity\Authentication\Models\PersonalAccessToken;
use App\Domains\Identity\Authentication\Workflows\AuthenticationWorkflowHook;
use App\Domains\Identity\Authentication\Workflows\NullAuthenticationWorkflowHook;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind( 
                        AuthenticationWorkflowHook::class, 
                        NullAuthenticationWorkflowHook::class, 
                        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }
}