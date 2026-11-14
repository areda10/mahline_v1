<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Identity\Customer;

use App\Domains\Identity\Authentication\Http\Middleware\EnsureActiveAuthenticationSession;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CustomerProfileTest extends TestCase
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

    public function test_customer_can_view_their_own_profile(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer)
            ->withoutMiddleware(EnsureActiveAuthenticationSession::class)
            ->get(route('customer.profile.edit'))
            ->assertOk()
            ->assertSee('Mon profil')
            ->assertSee('Salma')
            ->assertSee('client@example.com');
    }

    public function test_customer_can_update_their_personal_information(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer)
            ->withoutMiddleware(EnsureActiveAuthenticationSession::class)
            ->put(route('customer.profile.update'), [
                'first_name' => 'Nadia',
                'last_name' => 'Amrani',
                'telephone' => '+212 612345679',
            ])
            ->assertRedirect(route('customer.profile.edit'))
            ->assertSessionHas('success');

        $customer->refresh();

        $this->assertSame('Nadia', $customer->first_name);
        $this->assertSame('Amrani', $customer->last_name);
        $this->assertSame('Nadia Amrani', $customer->display_name);
        $this->assertSame('+212 612345679', $customer->telephone);

        $this->assertDatabaseHas('audit_events', [
            'event_key' => 'identity.customer.profile_updated',
            'subject_id' => $customer->getKey(),
        ]);
    }

    public function test_profile_update_cannot_change_email_status_or_role(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer)
            ->withoutMiddleware(EnsureActiveAuthenticationSession::class)
            ->put(route('customer.profile.update'), [
                'first_name' => 'Nadia',
                'last_name' => 'Amrani',
                'telephone' => '+212 612345679',
                'email' => 'attacker@example.com',
                'status' => UserStatus::Inactive->value,
                'cooperative_id' => '01J00000000000000000000000',
                'roles' => ['super_admin_root'],
            ])
            ->assertRedirect(route('customer.profile.edit'));

        $customer->refresh();

        $this->assertSame('client@example.com', $customer->email);
        $this->assertSame(UserStatus::Active, $customer->status);
        $this->assertNull($customer->cooperative_id);
        $this->assertTrue($customer->hasRole('customer'));
        $this->assertFalse($customer->hasRole('super_admin_root'));
    }

    public function test_internal_user_cannot_access_customer_profile(): void
    {
        $internalRole = Role::query()->firstOrCreate(
            ['slug' => 'user'],
            ['name' => 'User', 'is_active' => true],
        );

        $user = User::factory()->create([
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
        ]);
        $user->roles()->attach($internalRole->getKey());

        $this->actingAs($user)
            ->withoutMiddleware(EnsureActiveAuthenticationSession::class)
            ->get(route('customer.profile.edit'))
            ->assertForbidden();
    }

    public function test_customer_profile_is_denied_when_the_account_also_has_an_internal_role(): void
    {
        $customer = $this->customer();
        $internalRole = Role::query()->firstOrCreate(
            ['slug' => 'user'],
            ['name' => 'User', 'is_active' => true],
        );
        $customer->roles()->attach($internalRole->getKey());

        $this->actingAs($customer)
            ->withoutMiddleware(EnsureActiveAuthenticationSession::class)
            ->get(route('customer.profile.edit'))
            ->assertForbidden();
    }

    private function customer(): User
    {
        $customer = User::factory()->create([
            'first_name' => 'Salma',
            'last_name' => 'Client',
            'display_name' => 'Salma Client',
            'email' => 'client@example.com',
            'telephone' => '+212 612345678',
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
        ]);

        $customer->roles()->attach(
            Role::query()->where('slug', 'customer')->value('id'),
        );

        return $customer;
    }
}
