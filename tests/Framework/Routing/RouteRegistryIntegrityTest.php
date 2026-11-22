<?php

declare(strict_types=1);

namespace Tests\Framework\Routing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

final class RouteRegistryIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_registered_controller_route_resolves_to_an_existing_class_and_method(): void
    {
        foreach (RouteFacade::getRoutes() as $route) {
            self::assertInstanceOf(Route::class, $route);

            $controllerClass = $route->getControllerClass();
            if ($controllerClass === null) {
                continue;
            }

            self::assertTrue(
                class_exists($controllerClass),
                sprintf('Route [%s] references missing controller [%s].', $route->uri(), $controllerClass),
            );

            $method = $route->getActionMethod();
            self::assertTrue(
                method_exists($controllerClass, $method),
                sprintf(
                    'Route [%s] references missing action [%s::%s].',
                    $route->uri(),
                    $controllerClass,
                    $method,
                ),
            );
        }
    }

    public function test_registered_named_routes_do_not_have_duplicate_names(): void
    {
        $names = [];

        foreach (RouteFacade::getRoutes() as $route) {
            $name = $route->getName();
            if ($name === null) {
                continue;
            }

            $names[$name] = ($names[$name] ?? 0) + 1;
        }

        $duplicates = array_keys(array_filter(
            $names,
            static fn (int $count): bool => $count > 1,
        ));

        self::assertSame([], $duplicates, 'Duplicate route names: '.implode(', ', $duplicates));
    }
}
