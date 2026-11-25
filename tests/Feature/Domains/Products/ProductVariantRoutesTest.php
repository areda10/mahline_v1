<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Products;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class ProductVariantRoutesTest extends TestCase
{
    public function test_variant_management_routes_are_registered_under_the_parent_product(): void
    {
        $names = [
            'root.backoffice.cooperatives.products.variants.create',
            'root.backoffice.cooperatives.products.variants.store',
            'root.backoffice.cooperatives.products.variants.edit',
            'root.backoffice.cooperatives.products.variants.update',
            'root.backoffice.cooperatives.products.variants.activate',
            'root.backoffice.cooperatives.products.variants.deactivate',
            'root.backoffice.cooperatives.products.variants.destroy',
            'root.backoffice.cooperatives.products.variants.restore',
        ];

        $names = array_merge($names, [
            'backoffice.products.variants.create',
            'backoffice.products.variants.store',
            'backoffice.products.variants.edit',
            'backoffice.products.variants.update',
            'backoffice.products.variants.activate',
            'backoffice.products.variants.deactivate',
            'backoffice.products.variants.destroy',
            'backoffice.products.variants.restore',
        ]);

        foreach ($names as $name) {
            $this->assertTrue(Route::has($name), "La route {$name} doit être enregistrée.");
        }
    }

    public function test_variant_management_routes_are_restricted_to_the_root_backoffice(): void
    {
        $route = Route::getRoutes()->getByName('root.backoffice.cooperatives.products.variants.store');

        $this->assertNotNull($route);
        $this->assertContains('role:super_admin_root', $route->gatherMiddleware());
    }

    public function test_cooperative_backoffice_variant_routes_are_available_to_staff_roles_only(): void
    {
        $route = Route::getRoutes()->getByName('backoffice.products.variants.store');

        $this->assertNotNull($route);
        $this->assertContains('role:super_admin,cooperative_admin', $route->gatherMiddleware());
    }
}
