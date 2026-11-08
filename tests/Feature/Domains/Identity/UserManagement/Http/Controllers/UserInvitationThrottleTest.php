<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Identity\UserManagement\Http\Controllers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UserInvitationThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_acceptance_endpoint_is_limited_to_five_attempts_per_minute(): void
    {
        $payload = [
            'token' => 'invalid-invitation-token',
            'password' => 'MahlineSecure2026',
            'password_confirmation' => 'MahlineSecure2026',
        ];

        $this->withServerVariables([
            'REMOTE_ADDR' => '192.0.2.123',
        ]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('user-invitations.accept'), $payload)
                ->assertRedirect()
                ->assertSessionHasErrors('invitation');
        }

        $this->post(route('user-invitations.accept'), $payload)
            ->assertTooManyRequests();
    }
}
