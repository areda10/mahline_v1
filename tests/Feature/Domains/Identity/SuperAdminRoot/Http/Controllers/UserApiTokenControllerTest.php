<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Identity\SuperAdminRoot\Http\Controllers;

use App\Domains\Identity\Authentication\Http\Middleware\EnsureActiveAuthenticationSession;
use App\Domains\Identity\Authorization\Models\Permission;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UserApiTokenControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureActiveAuthenticationSession::class);
    }

    public function test_root_token_workflow_rejects_customer_role_even_when_account_is_suspended(): void
    {
        $root = User::factory()->create();
        $rootRole = Role::query()->firstOrCreate(
            ['slug' => 'super_admin_root'],
            ['name' => 'Super Admin Root', 'is_active' => true],
        );
        $viewUsers = Permission::query()->firstOrCreate(
            ['slug' => 'users.view'],
            ['name' => 'View users', 'is_active' => true],
        );
        $rootRole->permissions()->syncWithoutDetaching([$viewUsers->getKey()]);
        $root->roles()->attach($rootRole->getKey());

        $customer = User::factory()->create([
            'status' => UserStatus::Suspended,
            'email_verified_at' => null,
        ]);
        $customerRole = Role::query()->firstOrCreate(
            ['slug' => 'customer'],
            ['name' => 'Customer', 'is_active' => true],
        );
        $customer->roles()->attach($customerRole->getKey());

        $this->actingAs($root)
            ->post(route('root.backoffice.users.api-tokens.store', $customer), [
                'name' => 'forbidden-customer-token',
                'abilities' => [],
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
