<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Localization\Services;

use App\Domains\Localization\Contracts\CooperativeAddressOwnerGateway;
use App\Domains\Localization\Contracts\UserAddressOwnerGateway;
use App\Domains\Localization\Services\AddressService;
use App\Shared\Contracts\ActorContextProvider;
use ReflectionNamedType;
use Tests\TestCase;

final class AddressServiceBoundaryTest extends TestCase
{
    public function test_address_service_public_api_does_not_expose_identity_or_cooperative_models(): void
    {
        $reflection = new \ReflectionClass(AddressService::class);

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== AddressService::class) {
                continue;
            }

            foreach ($method->getParameters() as $parameter) {
                $type = $parameter->getType();

                if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
                    continue;
                }

                self::assertNotContains($type->getName(), [
                    'App\\Domains\\Identity\\Users\\Models\\User',
                    'App\\Domains\\Cooperatives\\Models\\Cooperative',
                ], sprintf(
                    'AddressService::%s must exchange owner IDs/DTOs, not cross-domain Eloquent models.',
                    $method->getName(),
                ));
            }
        }

        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);

        $dependencies = array_map(
            static fn ($parameter): ?string => ($parameter->getType() instanceof ReflectionNamedType)
                ? $parameter->getType()->getName()
                : null,
            $constructor->getParameters(),
        );

        self::assertContains(ActorContextProvider::class, $dependencies);
        self::assertContains(UserAddressOwnerGateway::class, $dependencies);
        self::assertContains(CooperativeAddressOwnerGateway::class, $dependencies);
    }
}
