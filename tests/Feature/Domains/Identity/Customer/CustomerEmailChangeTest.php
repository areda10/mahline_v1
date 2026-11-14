<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Identity\Customer;

use App\Domains\Identity\Authentication\Http\Middleware\EnsureActiveAuthenticationSession;
use App\Domains\Identity\Authentication\Models\AuthenticationSession;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Customer\Notifications\CustomerEmailChangeNotification;
use App\Domains\Identity\Customer\Notifications\CustomerEmailChangedNotification;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class CustomerEmailChangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::query()->firstOrCreate(
            ['slug' => 'customer'],
            ['name' => 'Customer', 'is_active' => true],
        );

        Notification::fake();
    }

    public function test_customer_must_provide_the_current_password_to_request_an_email_change(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer)
            ->withoutMiddleware(EnsureActiveAuthenticationSession::class)
            ->post(route('customer.profile.email.change'), [
                'new_email' => 'new@example.com',
                'current_password' => 'incorrect-password',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertDatabaseCount('customer_email_change_requests', 0);
        Notification::assertNothingSent();
    }

    public function test_customer_cannot_request_an_email_already_used_by_another_account(): void
    {
        $customer = $this->customer();
        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($customer)
            ->withoutMiddleware(EnsureActiveAuthenticationSession::class)
            ->post(route('customer.profile.email.change'), [
                'new_email' => 'taken@example.com',
                'current_password' => 'Customer123!',
            ])
            ->assertSessionHasErrors('new_email');

        $this->assertDatabaseCount('customer_email_change_requests', 0);
        Notification::assertNothingSent();
    }

    public function test_a_new_request_revokes_the_previous_pending_link(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer)
            ->withoutMiddleware(EnsureActiveAuthenticationSession::class)
            ->post(route('customer.profile.email.change'), [
                'new_email' => 'first@example.com',
                'current_password' => 'Customer123!',
            ])
            ->assertRedirect(route('customer.profile.edit'));

        $firstToken = $this->lastVerificationToken();

        $this->post(route('customer.profile.email.change'), [
            'new_email' => 'second@example.com',
            'current_password' => 'Customer123!',
        ])->assertRedirect(route('customer.profile.edit'));

        $this->assertDatabaseHas('customer_email_change_requests', [
            'new_email' => 'first@example.com',
        ]);

        $this->assertDatabaseHas('customer_email_change_requests', [
            'new_email' => 'second@example.com',
            'revoked_at' => null,
        ]);

        $this->get(route('customer.email.change.verify', ['token' => $firstToken]))
            ->assertRedirect(route('customer.login'));

        $this->assertSame('client@example.com', $customer->fresh()->email);
    }

    public function test_expired_email_change_link_cannot_be_used(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer)
            ->withoutMiddleware(EnsureActiveAuthenticationSession::class)
            ->post(route('customer.profile.email.change'), [
                'new_email' => 'new@example.com',
                'current_password' => 'Customer123!',
            ]);

        $token = $this->lastVerificationToken();

        $this->travel(31)->minutes();

        $this->get(route('customer.email.change.verify', ['token' => $token]))
            ->assertRedirect(route('customer.login'));

        $this->assertSame('client@example.com', $customer->fresh()->email);
    }

    public function test_verification_changes_email_and_revokes_all_sessions_and_api_tokens(): void
    {
        $customer = $this->customer();

        $session = AuthenticationSession::query()->create([
            'user_id' => $customer->getKey(),
            'session_id' => 'customer-email-change-session',
            'authenticated_at' => now(),
            'last_activity_at' => now(),
        ]);

        $token = $customer->createToken('customer-device')->accessToken;
        $this->assertNull($token->revoked_at);

        $this->actingAs($customer)
            ->withoutMiddleware(EnsureActiveAuthenticationSession::class)
            ->post(route('customer.profile.email.change'), [
                'new_email' => 'new@example.com',
                'current_password' => 'Customer123!',
            ])
            ->assertRedirect(route('customer.profile.edit'));

        $verificationToken = $this->lastVerificationToken();

        $this->get(route('customer.email.change.verify', ['token' => $verificationToken]))
            ->assertRedirect(route('customer.login'))
            ->assertSessionHas('status');

        $customer->refresh();

        $this->assertSame('new@example.com', $customer->email);
        $this->assertNotNull($session->fresh()->revoked_at);
        $this->assertSame('global_logout', $session->fresh()->revocation_reason);
        $this->assertNotNull($token->fresh()->revoked_at);

        Notification::assertSentOnDemand(CustomerEmailChangedNotification::class);
        $this->assertDatabaseHas('audit_events', [
            'event_key' => 'identity.customer.email_changed',
            'subject_id' => $customer->getKey(),
        ]);

        $this->get(route('customer.email.change.verify', ['token' => $verificationToken]))
            ->assertRedirect(route('customer.login'));

        $this->assertSame('new@example.com', $customer->fresh()->email);
    }

    private function lastVerificationToken(): string
    {
        $token = null;

        Notification::assertSentOnDemand(
            CustomerEmailChangeNotification::class,
            function (CustomerEmailChangeNotification $notification) use (&$token): bool {
                $query = parse_url($notification->verificationUrl(), PHP_URL_QUERY);
                parse_str((string) $query, $parameters);
                $token = $parameters['token'] ?? null;

                return is_string($token) && $token !== '';
            },
        );

        $this->assertIsString($token);

        return $token;
    }

    private function customer(): User
    {
        $customer = User::factory()->create([
            'first_name' => 'Salma',
            'last_name' => 'Client',
            'display_name' => 'Salma Client',
            'email' => 'client@example.com',
            'telephone' => '+212 612345678',
            'password' => 'Customer123!',
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
        ]);

        $customer->roles()->attach(
            Role::query()->where('slug', 'customer')->value('id'),
        );

        return $customer;
    }
}