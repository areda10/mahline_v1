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
        $rootRole = Role::query()->firstOrCreate(
            ['slug' => 'super_admin_root'],
            [
                'name' => 'Super Admin Root',
                'is_active' => true,
            ],
        );

        $root = User::factory()->create([
            'password' => 'password',
            'status' => UserStatus::Active,
        ]);

        $root->roles()->attach($rootRole->getKey());

        $response = $this->post(route('authentication.login.store'), [
            'email' => $root->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(
            route('root.backoffice.dashboard'),
        );

        $this->assertAuthenticatedAs($root);

        $sessionCookie = $response->getCookie(
            config('session.cookie'),
        );

        $this->assertNotNull($sessionCookie);

        $this->withCookie(
            config('session.cookie'),
            $sessionCookie->getValue(),
        );

        $dashboardResponse = $this->get(
            route('root.backoffice.dashboard'),
        );

        $dashboardResponse->assertOk();

        $this->assertAuthenticatedAs($root);
    }


    public function test_user_can_keep_multiple_active_web_sessions_on_different_devices(): void
    {
        $user = User::factory()->create([
            'email' => 'multiple-sessions@example.com',
            'password' => 'password',
            'status' => UserStatus::Active,
        ]);

        $firstResponse = $this
            ->withHeader('User-Agent', 'MAHLINE Desktop Browser')
            ->postJson($this->loginUrl, [
                'email' => $user->email,
                'password' => 'password',
            ]);

        $firstResponse->assertOk();

        $secondResponse = $this
            ->withHeader('User-Agent', 'MAHLINE Mobile Browser')
            ->postJson($this->loginUrl, [
                'email' => $user->email,
                'password' => 'password',
            ]);

        $secondResponse->assertOk();

        $sessions = AuthenticationSession::query()
            ->where('user_id', $user->getKey())
            ->orderBy('authenticated_at')
            ->get();

        $this->assertCount(2, $sessions);
        $this->assertNull($sessions[0]->revoked_at);
        $this->assertNull($sessions[1]->revoked_at);
        $this->assertNotSame(
            $sessions[0]->session_id,
            $sessions[1]->session_id,
        );
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
