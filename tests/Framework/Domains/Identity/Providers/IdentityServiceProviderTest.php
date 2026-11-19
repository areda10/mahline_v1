<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Providers;

use App\Domains\Identity\Authentication\Workflows\AuthenticationWorkflowHook;
use App\Domains\Identity\Authorization\Services\IdentityActorAuthorizer;
use App\Domains\Identity\Authorization\Services\IdentityActorContextProvider;
use App\Domains\Identity\Authorization\Contracts\SuperAdminCooperativeAssignmentReader;
use App\Domains\Identity\Authorization\Services\SuperAdminCooperativeAssignmentReaderService;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authorization\Models\Permission;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Identity\Authentication\Workflows\RecordNewDeviceAuthenticationWorkflowHook;
use App\Domains\Identity\UserManagement\Contracts\SuperAdminCooperativeAssignmentService;
use App\Domains\Identity\UserManagement\Services\UserManagementService;
use App\Shared\Contracts\ActorAuthorizer;
use App\Shared\Contracts\ActorContextProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

final class IdentityServiceProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_actor_authorizer_delegates_to_laravel_gate(): void
    {
        $user = User::factory()->create();

        Gate::define('identity.actor-context-test', static fn (User $actor): bool => $actor->isAuthorized());

        app(ActorAuthorizer::class)->authorize(
            (string) $user->getKey(),
            'identity.actor-context-test',
        );

        $user->forceFill(['emergency_locked_at' => now()])->save();

        $this->expectException(\\Illuminate\\Auth\\Access\\AuthorizationException::class);

        app(ActorAuthorizer::class)->authorize(
            (string) $user->getKey(),
            'identity.actor-context-test',
        );
    }

    public function test_actor_context_contains_only_active_roles_and_permissions(): void
    {
        $user = User::factory()->create();
        $activeRole = Role::query()->create([
            'name' => 'Context role',
            'slug' => 'context_role',
            'is_active' => true,
        ]);
        $inactiveRole = Role::query()->create([
            'name' => 'Inactive context role',
            'slug' => 'inactive_context_role',
            'is_active' => false,
        ]);
        $activePermission = Permission::query()->create([
            'name' => 'Context permission',
            'slug' => 'context.permission',
            'is_active' => true,
        ]);
        $inactivePermission = Permission::query()->create([
            'name' => 'Inactive context permission',
            'slug' => 'context.inactive',
            'is_active' => false,
        ]);

        $user->roles()->attach([$activeRole->getKey(), $inactiveRole->getKey()]);
        $activeRole->permissions()->attach([$activePermission->getKey(), $inactivePermission->getKey()]);

        $context = app(ActorContextProvider::class)->forUserId((string) $user->getKey());

        self::assertSame((string) $user->getKey(), $context->id);
        self::assertTrue($context->authorized);
        self::assertTrue($context->hasRole('context_role'));
        self::assertFalse($context->hasRole('inactive_context_role'));
        self::assertTrue($context->hasPermission('context.permission'));
        self::assertFalse($context->hasPermission('context.inactive'));
    }

    public function test_assignment_reader_uses_identity_owned_pivot(): void
    {
        $superAdmin = User::factory()->create();
        $cooperative = Cooperative::factory()->create();
        $reader = app(SuperAdminCooperativeAssignmentReader::class);

        self::assertFalse($reader->isAssigned(
            (string) $superAdmin->getKey(),
            (string) $cooperative->getKey(),
        ));

        $superAdmin->cooperatives()->attach($cooperative->getKey());

        self::assertTrue($reader->isAssigned(
            (string) $superAdmin->getKey(),
            (string) $cooperative->getKey(),
        ));
        self::assertInstanceOf(SuperAdminCooperativeAssignmentReaderService::class, $reader);
    }

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
