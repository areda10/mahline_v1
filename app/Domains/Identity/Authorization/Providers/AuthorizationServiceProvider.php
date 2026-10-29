<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authorization\Providers;

use App\Domains\Audit\Models\AuditEvent;
use App\Domains\Audit\Policies\AuditEventPolicy;
use App\Domains\Catalog\Models\Catalog;
use App\Domains\Catalog\Policies\CatalogPolicy;
use App\Domains\Categories\Models\Category;
use App\Domains\Categories\Policies\CategoryPolicy;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authorization\Policies\CooperativePolicy;
use App\Domains\Products\Models\Product;
use App\Domains\Products\Policies\ProductPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class AuthorizationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(AuditEvent::class, AuditEventPolicy::class);

        Gate::policy(
            Catalog::class,
            CatalogPolicy::class,
        );
        
        Gate::policy(
            Category::class,
            CategoryPolicy::class,
        );

        Gate::policy(
            Cooperative::class,
            CooperativePolicy::class,
        );

        Gate::policy(
            Product::class,
            ProductPolicy::class,
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

                // Les catalogues doivent être autorisés par CatalogPolicy.
                $isCatalogAuthorization =
                    in_array(Catalog::class, $arguments, true)
                    || count(array_filter(
                        $arguments,
                        static fn (mixed $argument): bool =>
                            $argument instanceof Catalog,
                    )) > 0;

                if ($isCatalogAuthorization) {
                    return null;
                }
                
                // Les catégories doivent être autorisées par CategoryPolicy.
                $isCategoryAuthorization =
                    in_array(Category::class, $arguments, true)
                    || count(array_filter(
                        $arguments,
                        static fn (mixed $argument): bool =>
                            $argument instanceof Category,
                    )) > 0;

                if ($isCategoryAuthorization) {
                    return null;
                }

                // Les produits doivent être autorisés par ProductPolicy.
                $isProductAuthorization =
                    in_array(Product::class, $arguments, true)
                    || count(array_filter(
                        $arguments,
                        static fn (mixed $argument): bool =>
                            $argument instanceof Product,
                    )) > 0;

                if ($isProductAuthorization) {
                    return null;
                }
                
                // Autorisation générique pour les autres domaines.
                if ($user->hasPermission($ability)) {
                    return true;
                }

                return null;
            },
        );
    }
}