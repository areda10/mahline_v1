<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Identity\Customer;

use App\Domains\Identity\Authentication\Http\Middleware\EnsureActiveAuthenticationSession;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class CustomerAuthenticationIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::query()->firstOrCreate(
            ['slug' => 'customer'],
            ['name' => 'Customer', 'is_active' => true],
        );
    }

    public function test_guest_sees_customer_login_form_with_customer_only_action(): void
    {
        $this->get(route('customer.login'))
            ->assertOk()
            ->assertSee(route('customer.login.store'), false)
            ->assertDontSee(route('authentication.login.store'), false);
    }

    public function test_customer_cannot_authenticate_through_internal_login(): void
    {
        $customer = $this->customer();
        $customer->forceFill(['password' => Hash::make('MosaicRiver728!')])->save();

        $this->post(route('authentication.login.store'), [
            'email' => $customer->email,
            'password' => 'MosaicRiver728!',
        ])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_internal_account_cannot_authenticate_through_customer_login(): void
    {
        $internalRole = Role::query()->firstOrCreate(
            ['slug' => 'super_admin'],
            ['name' => 'Super Admin', 'is_active' => true],
        );

        $internalUser = User::factory()->create([
            'email' => 'internal@example.com',
            'password' => Hash::make('MosaicRiver728!'),
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
        ]);
        $internalUser->roles()->attach($internalRole->getKey());

        $this->post(route('customer.login.store'), [
            'email' => $internalUser->email,
            'password' => 'MosaicRiver728!',
        ])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_customer_logout_uses_customer_login_route_and_ends_only_current_session(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer)
            ->withoutMiddleware(EnsureActiveAuthenticationSession::class)
            ->post(route('customer.logout'))
            ->assertRedirect(route('customer.login'));

        $this->assertGuest();
    }

    public function test_customer_cannot_access_internal_personal_security_routes(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer)
            ->withoutMiddleware(EnsureActiveAuthenticationSession::class)
            ->get(route('backoffice.security.sessions.index'))
            ->assertForbidden();
    }

    public function test_internal_account_cannot_use_customer_security_password_routes(): void
    {
        $internalRole = Role::query()->firstOrCreate(
            ['slug' => 'user'],
            ['name' => 'User', 'is_active' => true],
        );

        $internalUser = User::factory()->create([
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
        ]);
        $internalUser->roles()->attach($internalRole->getKey());

        $this->actingAs($internalUser)
            ->withoutMiddleware(EnsureActiveAuthenticationSession::class)
            ->get(route('customer.security.password.edit'))
            ->assertForbidden();
    }

    private function customer(): User
    {
        $customer = User::factory()->create([
            'email' => 'customer@example.com',
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
        ]);

        $customer->roles()->attach(
            Role::query()->where('slug', 'customer')->value('id'),
        );

        return $customer;
    }
}
