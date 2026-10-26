<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Products\Http\Controllers;

use App\Domains\Products\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class ProductReactivationRouteTest extends TestCase
{
    public function test_product_reactivation_route_is_registered_in_the_cooperative_scope(): void
    {
        $route = Route::getRoutes()->getByName(
            'backoffice.products.reactivate',
        );

        $this->assertNotNull($route);

        $this->assertContains('PATCH', $route->methods());

        $this->assertSame(
            'back-office/cooperatives/{cooperative}/products/{product}/reactivate',
            $route->uri(),
        );

        $this->assertSame(
            ProductController::class.'@reactivate',
            $route->getActionName(),
        );
    }
}
