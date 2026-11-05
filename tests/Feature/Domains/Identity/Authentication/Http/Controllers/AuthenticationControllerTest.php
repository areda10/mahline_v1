<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Identity\Authentication\Http\Controllers;

use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Authentication\Models\AuthenticationSession;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AuthenticationControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $loginUrl;

    private string $logoutUrl;

    private string $userAgent =
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
        . 'AppleWebKit/537.36 (KHTML, like Gecko) '
        . 'Chrome/140.0.0.0 Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();

        $this->loginUrl = route('authentication.login.store');
        $this->logoutUrl = route('authentication.logout');
    }

    public function test_guest_cannot_logout(): void
    {
        $response = $this->postJson($this->logoutUrl);

        $response->assertStatus(401);

        $response->assertJson([
            'message' => 'Unauthenticated.',
        ]);
    }

    public function test_authentication_request_validates_required_fields(): void
    {
        $response = $this->postJson($this->loginUrl, []);

        $response->assertStatus(422);

        $response->assertJsonPath(
            'errors.email',
            fn ($errors): bool =>
                is_array($errors) && $errors !== [],
        );

        $response->assertJsonPath(
            'errors.password',
            fn ($errors): bool =>
                is_array($errors) && $errors !== [],
        );
    }

    public function test_invalid_email_is_rejected(): void
    {
        $response = $this->postJson(
            $this->loginUrl,
            [
                'email' => 'not-an-email',
                'password' => 'password',
            ],
        );

        $response->assertStatus(422);

        $response->assertJsonPath(
            'errors.email',
            fn ($errors): bool =>
                is_array($errors) && $errors !== [],
        );
    }

    public function test_active_user_with_unverified_email_cannot_authenticate(): void
    {
        $user = User::factory()->create([
            'email' => 'unverified-controller@example.com',
            'password' => 'password',
            'status' => UserStatus::Active,
            'email_verified_at' => null,
        ]);

        $response = $this
            ->withHeader('User-Agent', $this->userAgent)
            ->postJson(
                $this->loginUrl,
                [
                    'email' => $user->email,
                    'password' => 'password',
                ],
            );

        $response->assertUnauthorized();

        $response->assertJson([
            'message' => 'Email address is not verified.',
        ]);

        $this->assertGuest();

        $this->assertDatabaseMissing('authentication_sessions', [
            'user_id' => $user->getKey(),
        ]);
    }


    public function test_super_admin_root_is_redirected_to_its_dedicated_dashboard_after_login(): void
    {
        $role = Role::query()->create([
            'slug' => 'super_admin_root',
            'name' => 'Super Admin Root',
            'is_active' => true,
        ]);

        $root = User::factory()->create([
            'email' => 'root-dashboard@example.com',
            'password' => 'RootPassword123!',
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
        ]);

        $root->roles()->attach($role->getKey());

        $response = $this
            ->withHeader('User-Agent', $this->userAgent)
            ->post($this->loginUrl, [
                'email' => $root->email,
                'password' => 'RootPassword123!',
            ]);

        $response->assertRedirect(route('root.backoffice.dashboard'));

        $this->assertAuthenticatedAs($root);

        $authenticationSessionId = session('authentication_session_id');

        $this->assertIsString($authenticationSessionId);

        $authenticationSession = AuthenticationSession::query()
            ->whereKey($authenticationSessionId)
            ->where('user_id', $root->getKey())
            ->firstOrFail();

        $this->assertSame(
            session()->getId(),
            $authenticationSession->session_id,
        );

        $this->get(route('root.backoffice.dashboard'))
            ->assertOk()
            ->assertViewIs('super-admin-root.dashboard');
    }

    public function test_user_can_authenticate_successfully(): void
    {
        $user = User::factory()->create([
            'email' => 'controller@example.com',
            'password' => 'password',
            'status' => UserStatus::Active,
        ]);

        $response = $this
            ->withHeader('User-Agent', $this->userAgent)
            ->postJson(
                $this->loginUrl,
                [
                    'email' => $user->email,
                    'password' => 'password',
                ],
            );

        $response->assertOk();

        $response->assertJson([
            'message' => 'Authentication successful.',
        ]);

        $response->assertJsonPath(
            'user.id',
            $user->getKey(),
        );

        $response->assertJsonPath(
            'user.email',
            $user->email,
        );

        $this->assertAuthenticatedAs($user);
    }
}
