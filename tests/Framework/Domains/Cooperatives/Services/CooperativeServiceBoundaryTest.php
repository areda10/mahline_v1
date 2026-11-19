<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Cooperatives\Services;

use App\Domains\Cooperatives\Services\CooperativeService;
use App\Shared\Contracts\ActorAuthorizer;
use App\Shared\Contracts\ActorContextProvider;
use ReflectionNamedType;
use Tests\TestCase;

final class CooperativeServiceBoundaryTest extends TestCase
{
    public function test_cooperative_service_uses_shared_actor_contracts_not_identity_models(): void
    {
        $reflection = new \ReflectionClass(CooperativeService::class);

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== CooperativeService::class) {
                continue;
            }

            foreach ($method->getParameters() as $parameter) {
                $type = $parameter->getType();

                if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
                    continue;
                }

                self::assertNotSame(
                    'App\\Domains\\Identity\\Users\\Models\\User',
                    $type->getName(),
                    sprintf('CooperativeService::%s must receive actor IDs, not User models.', $method->getName()),
                );
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
        self::assertContains(ActorAuthorizer::class, $dependencies);
    }
}
