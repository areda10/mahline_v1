<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Identity\Customer;

use App\Domains\Identity\Authentication\Http\Middleware\EnsureActiveAuthenticationSession;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Localization\Models\Country;
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

        Country::query()->firstOrCreate(
            ['code' => 'MA'],
            [
                'iso3' => 'MAR',
                'name' => 'Maroc',
                'native_name' => 'المغرب',
                'phone_code' => '+212',
                'currency' => 'MAD',
                'locale' => 'fr',
                'is_active' => true,
            ],
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
            ->assertSee('client@example.com')
            ->assertDontSee('Photo de profil')
            ->assertDontSee('domaine Media');
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
                'number' => '10',
                'street' => 'Avenue Hassan II',
                'floor' => '2e étage',
                'city' => 'Casablanca',
                'region' => 'Casablanca-Settat',
                'postal_code' => '20000',
            ])
            ->assertRedirect(route('customer.profile.edit'))
            ->assertSessionHas('success');

        $customer->refresh();

        $this->assertSame('Nadia', $customer->first_name);
        $this->assertSame('Amrani', $customer->last_name);
        $this->assertSame('Nadia Amrani', $customer->display_name);
        $this->assertSame('+212 612345679', $customer->telephone);
        $this->assertDatabaseHas('addresses', [
            'user_id' => $customer->getKey(),
            'number' => '10',
            'street' => 'Avenue Hassan II',
            'city' => 'Casablanca',
            'region' => 'Casablanca-Settat',
        ]);

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
                'number' => '10',
                'street' => 'Avenue Hassan II',
                'city' => 'Casablanca',
                'region' => 'Casablanca-Settat',
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
            ->get(route('customer.dashboard'))
            ->assertForbidden();

        $this->actingAs($customer)
            ->withoutMiddleware(EnsureActiveAuthenticationSession::class)
            ->get(route('customer.profile.edit'))
            ->assertForbidden();
    }

    public function test_customer_can_add_select_and_soft_delete_saved_addresses(): void
    {
        $customer = $this->customer();
        $this->actingAs($customer)->withoutMiddleware(EnsureActiveAuthenticationSession::class);

        $this->post(route('customer.profile.addresses.store'), [
            'street' => 'Rue Principale', 'city' => 'Casablanca', 'region' => 'Casablanca-Settat',
        ])->assertRedirect(route('customer.profile.edit'));

        $first = $customer->addresses()->firstOrFail();

        $this->post(route('customer.profile.addresses.store'), [
            'street' => 'Rue Secondaire', 'city' => 'Rabat', 'region' => 'Rabat-Salé-Kénitra',
        ])->assertRedirect(route('customer.profile.edit'));

        $second = $customer->addresses()->where('street', 'Rue Secondaire')->firstOrFail();
        self::assertTrue($first->fresh()->is_primary);
        self::assertFalse($second->is_primary);

        $this->patch(route('customer.profile.addresses.primary', $second))
            ->assertRedirect(route('customer.profile.edit'));
        self::assertTrue($second->fresh()->is_primary);
        self::assertFalse($first->fresh()->is_primary);

        $this->delete(route('customer.profile.addresses.destroy', $second))
            ->assertRedirect(route('customer.profile.edit'));
        $this->assertSoftDeleted('addresses', ['id' => $second->getKey()]);
        self::assertTrue($first->fresh()->is_primary);
    }

    public function test_customer_can_update_a_secondary_address_without_changing_the_primary_address(): void
    {
        $customer = $this->customer();
        $this->actingAs($customer)->withoutMiddleware(EnsureActiveAuthenticationSession::class);

        $this->post(route('customer.profile.addresses.store'), [
            'street' => 'Rue Principale',
            'city' => 'Casablanca',
            'region' => 'Casablanca-Settat',
        ])->assertRedirect(route('customer.profile.edit'));

        $this->post(route('customer.profile.addresses.store'), [
            'street' => 'Rue Secondaire',
            'city' => 'Rabat',
            'region' => 'Rabat-Salé-Kénitra',
        ])->assertRedirect(route('customer.profile.edit'));

        $primary = $customer->addresses()->where('is_primary', true)->firstOrFail();
        $secondary = $customer->addresses()->where('street', 'Rue Secondaire')->firstOrFail();

        $this->patch(route('customer.profile.addresses.update', $secondary), [
            'number' => '25',
            'street' => 'Avenue Al Massira',
            'floor' => '3e étage',
            'city' => 'Rabat',
            'region' => 'Rabat-Salé-Kénitra',
            'postal_code' => '10000',
        ])->assertRedirect(route('customer.profile.edit'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('addresses', [
            'id' => $secondary->getKey(),
            'user_id' => $customer->getKey(),
            'number' => '25',
            'street' => 'Avenue Al Massira',
            'floor' => '3e étage',
            'city' => 'Rabat',
            'postal_code' => '10000',
            'is_primary' => false,
        ]);
        self::assertTrue($primary->fresh()->is_primary);

        $this->assertDatabaseHas('audit_events', [
            'event_key' => 'localization.address.updated',
            'subject_id' => $secondary->getKey(),
        ]);
    }

    public function test_customer_cannot_update_another_customers_address(): void
    {
        $customer = $this->customer();
        $otherCustomer = $this->customer();
        $this->actingAs($customer)->withoutMiddleware(EnsureActiveAuthenticationSession::class);

        $foreignAddress = app(\\App\\Domains\\Localization\\Contracts\\UserAddressLifecycleManager::class)
            ->createForUser((string) $otherCustomer->getKey(), (string) $otherCustomer->getKey(), [
                'street' => 'Rue privée',
                'city' => 'Fès',
                'region' => 'Fès-Meknès',
            ]);

        $this->patch(route('customer.profile.addresses.update', $foreignAddress), [
            'street' => 'Tentative',
            'city' => 'Casablanca',
        ])->assertNotFound();

        $this->assertDatabaseHas('addresses', [
            'id' => $foreignAddress->getKey(),
            'street' => 'Rue privée',
            'city' => 'Fès',
        ]);
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
