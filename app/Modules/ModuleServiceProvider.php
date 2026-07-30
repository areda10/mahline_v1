<?php

declare(strict_types=1);

namespace App\Modules;

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Enregistre les services des modules.
     */
    public function register(): void
    {
        $this->registerModuleProviders();
    }

    /**
     * Démarre les modules.
     */
    public function boot(): void
    {
        $this->loadModuleRoutes();
    }

    /**
     * Enregistre automatiquement les ServiceProvider des modules.
     */
    protected function registerModuleProviders(): void
    {
        $modulesPath = app_path('Modules');

        if (! File::exists($modulesPath)) {
            return;
        }

        foreach (File::directories($modulesPath) as $modulePath) {

            $module = basename($modulePath);

            $provider = "App\\Modules\\{$module}\\{$module}ServiceProvider";

            if (class_exists($provider)) {
                $this->app->register($provider);
            }
        }
    }

    /**
     * Charge automatiquement les routes des modules.
     */
    protected function loadModuleRoutes(): void
    {
        $modulesPath = app_path('Modules');

        if (! File::exists($modulesPath)) {
            return;
        }

        foreach (File::directories($modulesPath) as $modulePath) {

            $routes = $modulePath . DIRECTORY_SEPARATOR . 'routes.php';

            if (File::exists($routes)) {
                $this->loadRoutesFrom($routes);
            }
        }
    }
}
