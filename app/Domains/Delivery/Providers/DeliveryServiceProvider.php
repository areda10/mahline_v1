<?php

declare(strict_types=1);

namespace App\Domains\Delivery\Providers;

use App\Domains\Delivery\Contracts\DeliveryQuoteProvider;
use App\Domains\Delivery\Services\InitialFlatRateDeliveryQuoteProvider;
use Illuminate\Support\ServiceProvider;

final class DeliveryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DeliveryQuoteProvider::class, InitialFlatRateDeliveryQuoteProvider::class);
    }
}
