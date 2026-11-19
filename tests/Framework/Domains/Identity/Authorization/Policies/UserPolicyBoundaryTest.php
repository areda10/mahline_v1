<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Authorization\Policies;

use App\Domains\Cooperatives\Contracts\CooperativeIdentityReference;
use App\Domains\Identity\Authorization\Policies\UserPolicy;
use App\Shared\Contracts\SuperAdminCooperativeAssignmentReader;
use ReflectionNamedType;
use Tests\TestCase;

final class UserPolicyBoundaryTest extends TestCase
{
    public function test_user_policy_uses_public_cooperative_contracts_not_cooperative_models(): void
    {
        $reflection = new \ReflectionClass(UserPolicy::class);

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== UserPolicy::class) {
                continue;
            }

            foreach ($method->getParameters() as $parameter) {
                $type = $parameter->getType();

                if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
                    continue;
                }

                self::assertNotSame(
                    'App\\Domains\\Cooperatives\\Models\\Cooperative',
                    $type->getName(),
                    sprintf('UserPolicy::%s must accept cooperative IDs, not Cooperative models.', $method->getName()),
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

        self::assertContains(CooperativeIdentityReference::class, $dependencies);
        self::assertContains(SuperAdminCooperativeAssignmentReader::class, $dependencies);
    }
}
