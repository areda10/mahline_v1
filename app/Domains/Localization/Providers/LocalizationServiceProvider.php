<?php

declare(strict_types=1);

namespace App\Domains\Localization\Providers;

use App\Domains\Localization\Contracts\CheckoutAddressReader;
use App\Domains\Localization\Contracts\CooperativeAddressManager;
use App\Domains\Localization\Contracts\UserAddressLifecycleManager;
use App\Domains\Localization\Services\AddressService;
use App\Domains\Localization\Services\CheckoutAddressSnapshotService;
use Illuminate\Support\ServiceProvider;

final class LocalizationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CooperativeAddressManager::class, AddressService::class);
        $this->app->bind(UserAddressLifecycleManager::class, AddressService::class);
        $this->app->bind(CheckoutAddressReader::class, CheckoutAddressSnapshotService::class);
    }
}
