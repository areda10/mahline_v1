<?php

declare(strict_types=1);

namespace App\Core\Database\Providers;

use App\Core\Database\Macros\BlueprintMacros;
use Illuminate\Support\ServiceProvider;

final class DatabaseMacroServiceProvider extends ServiceProvider
{
    /**
     * Register database macro services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap database macros.
     */
    public function boot(): void
    {
        BlueprintMacros::register();
    }
}