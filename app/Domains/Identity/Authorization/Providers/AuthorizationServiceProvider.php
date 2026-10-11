<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authorization\Providers;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authorization\Policies\CooperativePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class AuthorizationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(
            Cooperative::class,
            CooperativePolicy::class,
        );

        Gate::before(
            function ($user, string $ability, ...$arguments): ?bool {
                $cooperativeAbilities = [
                    'viewAny',
                    'view',
                    'create',
                    'update',
                    'delete',
                ];

                $isCooperativeAuthorization = in_array(
                    $ability,
                    $cooperativeAbilities,
                    true,
                ) && (
                    in_array(Cooperative::class, $arguments, true)
                    || count(array_filter(
                        $arguments,
                        static fn (mixed $argument): bool =>
                            $argument instanceof Cooperative,
                    )) > 0
                );

                if ($isCooperativeAuthorization) {
                    return null;
                }

                if ($user->hasPermission($ability)) {
                    return true;
                }

                return null;
            },
        );
    }
}