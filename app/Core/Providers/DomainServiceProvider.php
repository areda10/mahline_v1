<?php

declare(strict_types=1);

namespace App\Core\Providers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

class DomainServiceProvider extends ServiceProvider
{
    /**
     * Enregistre les Service Providers des domaines.
     */
    public function register(): void
    {
        $this->registerDomainProviders();
    }

    /**
     * Démarre les domaines.
     */
    public function boot(): void
    {
        $this->loadDomainRoutes();
    }

    /**
     * Enregistre automatiquement les Service Providers des domaines.
     */
    protected function registerDomainProviders(): void
    {
        $domainsPath = app_path('Domains');

        if (! File::isDirectory($domainsPath)) {
            return;
        }

        foreach (File::directories($domainsPath) as $domainPath) {
            $domain = basename($domainPath);

            foreach (File::directories($domainPath) as $modulePath) {
                $module = basename($modulePath);

                $provider = "App\\Domains\\{$domain}\\{$module}\\{$module}ServiceProvider";

                if (class_exists($provider)) {
                    $this->app->register($provider);
                }
            }
        }
    }

    /**
     * Charge automatiquement les routes des domaines.
     */
    protected function loadDomainRoutes(): void
    {
        $domainsPath = app_path('Domains');

        if (! File::isDirectory($domainsPath)) {
            return;
        }

        foreach (File::directories($domainsPath) as $domainPath) {
            foreach (File::directories($domainPath) as $modulePath) {
                $routes = $modulePath . DIRECTORY_SEPARATOR . 'routes.php';

                if (File::exists($routes)) {
                    $this->loadRoutesFrom($routes);
                }
            }
        }
    }
}