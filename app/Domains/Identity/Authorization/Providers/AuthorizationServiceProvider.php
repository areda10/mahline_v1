<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authorization\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class AuthorizationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::before(function ($user, string $ability): ?bool {
            if ($user->hasPermission($ability)) {
                return true;
            }

            return null;
        });
    }
}