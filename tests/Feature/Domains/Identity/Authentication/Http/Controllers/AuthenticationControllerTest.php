<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Identity\Authentication\Http\Controllers;

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
