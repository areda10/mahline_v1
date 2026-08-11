<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Authentication\Services;

use App\Core\Security\Services\PasswordService;
use App\Domains\Identity\Authentication\Services\AuthenticationService;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

final class AuthenticationServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuthenticationService $authenticationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authenticationService = app(
            AuthenticationService::class
        );
    }

    public function test_authenticate_returns_user_with_valid_credentials(): void
    {
        $password = 'MahlinePassword123!';

        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => $password,
            'status' => 'active',
        ]);

        $authenticatedUser = $this->authenticationService->authenticate(
            'user@example.com',
            $password,
        );

        $this->assertInstanceOf(
            User::class,
            $authenticatedUser
        );

        $this->assertSame(
            $user->id,
            $authenticatedUser->id
        );
    }

    public function test_authenticate_rejects_invalid_password(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => 'CorrectPassword123!',
            'status' => 'active',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid credentials.');

        $this->authenticationService->authenticate(
            'user@example.com',
            'WrongPassword123!',
        );
    }

    public function test_authenticate_rejects_unknown_user(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->authenticationService->authenticate(
            'unknown@example.com',
            'AnyPassword123!',
        );
    }

    public function test_authenticate_rejects_inactive_user(): void
    {
        User::factory()->create([
            'email' => 'inactive@example.com',
            'password' => 'CorrectPassword123!',
            'status' => 'inactive',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('User account is not active.');

        $this->authenticationService->authenticate(
            'inactive@example.com',
            'CorrectPassword123!',
        );
    }

    public function test_authenticate_accepts_active_user_only(): void
    {
        $activeUser = User::factory()->create([
            'email' => 'active@example.com',
            'password' => 'CorrectPassword123!',
            'status' => 'active',
        ]);

        $result = $this->authenticationService->authenticate(
            'active@example.com',
            'CorrectPassword123!',
        );

        $this->assertSame(
            $activeUser->id,
            $result->id
        );
    }

    public function test_authentication_service_does_not_depend_on_request(): void
    {
        $constructor = new \ReflectionMethod(
            AuthenticationService::class,
            '__construct'
        );

        foreach ($constructor->getParameters() as $parameter) {
            $this->assertNotSame(
                \Illuminate\Http\Request::class,
                $parameter->getType()?->getName()
            );
        }
    }

    public function test_authentication_service_uses_password_service(): void
    {
        $this->assertInstanceOf(
            PasswordService::class,
            app(PasswordService::class)
        );

        $reflection = new \ReflectionClass(
            AuthenticationService::class
        );

        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);

        $parameters = $constructor->getParameters();

        $this->assertCount(1, $parameters);

        $this->assertSame(
            PasswordService::class,
            $parameters[0]->getType()?->getName()
        );
    }
}
