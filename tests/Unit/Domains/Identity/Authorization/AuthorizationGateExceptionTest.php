<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Identity\Authorization;

use App\Domains\Identity\Users\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

final class AuthorizationGateExceptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_gate_authorize_throws_for_user_without_permission(): void
    {
        $user = User::factory()->create();

        $this->expectException(AuthorizationException::class);

        Gate::forUser($user)->authorize('users.manage');
    }

    public function test_gate_authorize_denies_unknown_permission(): void
    {
        $user = User::factory()->create();

        $this->expectException(AuthorizationException::class);

        Gate::forUser($user)->authorize('permission.does.not.exist');
    }
}