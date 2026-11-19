<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Providers;

use App\Domains\Identity\Authentication\Workflows\AuthenticationWorkflowHook;
use App\Domains\Identity\Authorization\Services\IdentityActorAuthorizer;
use App\Domains\Identity\Authorization\Services\IdentityActorContextProvider;
use App\Domains\Identity\Authentication\Workflows\RecordNewDeviceAuthenticationWorkflowHook;
use App\Domains\Identity\UserManagement\Contracts\SuperAdminCooperativeAssignmentService;
use App\Domains\Identity\UserManagement\Services\UserManagementService;
use App\Shared\Contracts\ActorAuthorizer;
use App\Shared\Contracts\ActorContextProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class IdentityServiceProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_actor_contracts_are_bound_to_identity_adapters(): void
    {
        self::assertInstanceOf(
            IdentityActorContextProvider::class,
            app(ActorContextProvider::class),
        );

        self::assertInstanceOf(
            IdentityActorAuthorizer::class,
            app(ActorAuthorizer::class),
        );
    }

    public function test_identity_contracts_are_bound_by_the_identity_domain_provider(): void
    {
        self::assertInstanceOf(
            UserManagementService::class,
            app(SuperAdminCooperativeAssignmentService::class),
        );

        self::assertInstanceOf(
            RecordNewDeviceAuthenticationWorkflowHook::class,
            app(AuthenticationWorkflowHook::class),
        );
    }
}
