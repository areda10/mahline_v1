<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Identity\Customer;

use App\Domains\Identity\Authentication\Http\Middleware\EnsureActiveAuthenticationSession;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Customer\Notifications\CustomerEmailVerificationNotification;
use App\Domains\Identity\Customer\Services\CustomerRegistrationService;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Tests\TestCase;

final class CustomerRegistrationTest extends TestCase
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

    public function test_guest_can_register_a_pending_customer_and_verify_email_to_activate_account(): void
    {
        Notification::fake();

        $response = $this->post(route('customer.register.store'), $this->validRegistration());

        $response->assertRedirect(route('customer.verification.notice'));
        $this->assertGuest();

        $customer = User::query()
            ->where('email', 'client@example.com')
            ->firstOrFail();

        $this->assertSame(UserStatus::Pending, $customer->status);
        $this->assertNull($customer->email_verified_at);
        $this->assertTrue($customer->roles()->where('slug', 'customer')->exists());
        $this->assertFalse($customer->roles()->whereIn('slug', [
            'super_admin_root',
            'super_admin',
            'cooperative_admin',
            'user',
        ])->exists());

        $verification = \DB::table('customer_email_verifications')
            ->where('user_id', $customer->getKey())
            ->first();

        $this->assertNotNull($verification);
        $this->assertSame(
            now()->addHours(24)->format('Y-m-d H:i'),
            \Illuminate\Support\Carbon::parse($verification->expires_at)->format('Y-m-d H:i'),
        );

        $token = $this->sentVerificationToken();
        $this->assertDatabaseHas('customer_email_verifications', [
            'user_id' => $customer->getKey(),
            'token_hash' => hash('sha256', $token),
        ]);

        $this->get(route('customer.verification.verify', ['token' => $token]))
            ->assertRedirect(route('authentication.login'));

        $customer->refresh();

        $this->assertSame(UserStatus::Active, $customer->status);
        $this->assertNotNull($customer->email_verified_at);
        $this->assertNotNull(
            \DB::table('customer_email_verifications')
                ->where('user_id', $customer->getKey())
                ->value('used_at'),
        );

        $loginResponse = $this->post(route('customer.login.store'), [
            'email' => $customer->email,
            'password' => 'MosaicRiver728!',
        ]);

        $loginResponse->assertRedirect(route('customer.dashboard'));
        $this->assertAuthenticatedAs($customer);
        $this->get(route('customer.dashboard'))->assertOk();

        $sessionCookie = $loginResponse->getCookie(config('session.cookie'));
        $this->assertNotNull($sessionCookie);
        $this->assertSame(0, $sessionCookie->getExpiresTime());
    }

    public function test_expired_verification_link_does_not_activate_customer(): void
    {
        Notification::fake();

        $this->post(route('customer.register.store'), $this->validRegistration())
            ->assertRedirect(route('customer.verification.notice'));

        $customer = User::query()
            ->where('email', 'client@example.com')
            ->firstOrFail();

        \DB::table('customer_email_verifications')
            ->where('user_id', $customer->getKey())
            ->update(['expires_at' => now()->subSecond()]);

        $token = $this->sentVerificationToken();

        $this->get(route('customer.verification.verify', ['token' => $token]))
            ->assertRedirect(route('customer.verification.notice'));

        $this->assertSame(UserStatus::Pending, $customer->fresh()->status);
        $this->assertNull($customer->fresh()->email_verified_at);
    }

    public function test_registration_does_not_disclose_an_existing_email_address(): void
    {
        Notification::fake();

        $this->post(route('customer.register.store'), $this->validRegistration())
            ->assertRedirect(route('customer.verification.notice'));

        $this->post(route('customer.register.store'), $this->validRegistration())
            ->assertRedirect(route('customer.verification.notice'));

        $this->assertSame(
            1,
            User::query()->where('email', 'client@example.com')->count(),
        );

        Notification::assertSentOnDemand(CustomerEmailVerificationNotification::class);
    }

    public function test_resending_verification_replaces_the_previous_link_and_blocks_after_five_requests(): void
    {
        Notification::fake();

        $this->post(route('customer.register.store'), $this->validRegistration())
            ->assertRedirect(route('customer.verification.notice'));

        $customer = User::query()
            ->where('email', 'client@example.com')
            ->firstOrFail();

        $initialHash = DB::table('customer_email_verifications')
            ->where('user_id', $customer->getKey())
            ->value('token_hash');

        $service = app(CustomerRegistrationService::class);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $service->resendVerification($customer->email);
        }

        $this->assertDatabaseHas('customer_email_verifications', [
            'user_id' => $customer->getKey(),
            'token_hash' => $initialHash,
        ]);

        $this->assertNotNull(
            DB::table('customer_email_verifications')
                ->where('token_hash', $initialHash)
                ->value('revoked_at'),
        );

        $this->assertSame(
            1,
            DB::table('customer_email_verifications')
                ->where('user_id', $customer->getKey())
                ->whereNull('used_at')
                ->whereNull('revoked_at')
                ->count(),
        );

        $this->expectException(TooManyRequestsHttpException::class);
        $service->resendVerification($customer->email);
    }

    public function test_internal_user_cannot_access_customer_dashboard(): void
    {
        $role = Role::query()->firstOrCreate(
            ['slug' => 'user'],
            ['name' => 'User', 'is_active' => true],
        );

        $user = User::factory()->create([
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
        ]);
        $user->roles()->attach($role->getKey());

        $this->actingAs($user)
            ->withoutMiddleware(EnsureActiveAuthenticationSession::class)
            ->get(route('customer.dashboard'))
            ->assertForbidden();
    }

    public function test_customer_cannot_access_internal_user_dashboard(): void
    {
        $customer = User::factory()->create([
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
        ]);
        $customer->roles()->attach(
            Role::query()->where('slug', 'customer')->value('id'),
        );

        $this->actingAs($customer)
            ->withoutMiddleware(EnsureActiveAuthenticationSession::class)
            ->get(route('user.dashboard'))
            ->assertForbidden();
    }

    private function validRegistration(): array
    {
        return [
            'first_name' => 'Salma',
            'last_name' => 'Client',
            'email' => 'client@example.com',
            'telephone' => '+212 612345678',
            'password' => 'MosaicRiver728!',
            'password_confirmation' => 'MosaicRiver728!',
        ];
    }

    private function sentVerificationToken(): string
    {
        $token = null;

        Notification::assertSentOnDemand(
            CustomerEmailVerificationNotification::class,
            function (CustomerEmailVerificationNotification $notification) use (&$token): bool {
                $mail = $notification->toMail(new \stdClass());
                $query = parse_url((string) $mail->actionUrl, PHP_URL_QUERY);
                parse_str(is_string($query) ? $query : '', $parameters);
                $token = $parameters['token'] ?? null;

                return is_string($token) && $token !== '';
            },
        );

        $this->assertIsString($token);

        return $token;
    }
}
