<?php

declare(strict_types=1);

namespace App\Domains\Localization\Providers;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Localization\Models\Address;
use App\Domains\Localization\Policies\AddressPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class LocalizationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Address::class, AddressPolicy::class);

        Gate::define(
            'updateUserAddress',
            fn (User $actor, User $target): bool => app(AddressPolicy::class)
                ->updateUserAddress($actor, $target),
        );

        Gate::define(
            'createCooperativeAddress',
            fn (User $actor, Cooperative $cooperative): bool => app(AddressPolicy::class)
                ->createCooperativeAddress($actor, $cooperative),
        );

        Gate::define(
            'updateCooperativeAddress',
            fn (User $actor, Cooperative $cooperative): bool => app(AddressPolicy::class)
                ->updateCooperativeAddress($actor, $cooperative),
        );
    }
}
