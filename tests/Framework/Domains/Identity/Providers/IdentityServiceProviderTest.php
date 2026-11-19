<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Providers;

use App\Domains\Identity\Authentication\Workflows\AuthenticationWorkflowHook;
use App\Domains\Identity\Authentication\Workflows\RecordNewDeviceAuthenticationWorkflowHook;
use App\Domains\Identity\UserManagement\Contracts\SuperAdminCooperativeAssignmentService;
use App\Domains\Identity\UserManagement\Services\UserManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class IdentityServiceProviderTest extends TestCase
{
    use RefreshDatabase;

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
