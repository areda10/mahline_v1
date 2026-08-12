<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Identity\Authentication\Models\AuthenticationSession;
use App\Domains\Identity\Authentication\Models\LoginHistory;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoginHistory>
 */
final class LoginHistoryFactory extends Factory
{
    /**
     * The associated model.
     */
    protected $model = LoginHistory::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            /*
             * The user may be null for an unknown email.
             */
            'user_id' => User::factory(),

            /*
             * Email used during authentication.
             */
            'email' => fake()->unique()->safeEmail(),

            /*
             * Default event.
             */
            'event' => 'success',

            /*
             * No reason for a successful authentication.
             */
            'reason' => null,

            /*
             * A successful authentication may be associated
             * with an authentication session.
             */
            'authentication_session_id' => null,

            /*
             * Client information.
             */
            'ip_address' => fake()->ipv4(),

            'user_agent' => fake()->userAgent(),

            'browser' => 'Firefox',

            'device' => 'Desktop',

            /*
             * Authentication event timestamp.
             */
            'occurred_at' => now(),

            /*
             * Audit actors.
             */
            'created_by' => null,

            'updated_by' => null,
        ];
    }

    /**
     * Create a successful login history entry.
     */
    public function successful(): static
    {
        return $this->state([
            'event' => 'success',
            'reason' => null,
        ]);
    }

    /**
     * Create a failed authentication history entry.
     */
    public function failed(
        string $reason = 'invalid_credentials',
    ): static {
        return $this->state([
            'event' => 'failed',
            'reason' => $reason,
            'authentication_session_id' => null,
        ]);
    }

    /**
     * Create a logout history entry.
     */
    public function logout(): static
    {
        return $this->state([
            'event' => 'logout',
            'reason' => 'logout',
        ]);
    }

    /**
     * Create a session-revocation history entry.
     */
    public function sessionRevoked(
        string $reason = 'new_login',
    ): static {
        return $this->state([
            'event' => 'session_revoked',
            'reason' => $reason,
        ]);
    }

    /**
     * Associate the history entry with an authentication session.
     */
    public function forSession(
        AuthenticationSession $session,
    ): static {
        return $this->state([
            'user_id' => $session->user_id,
            'authentication_session_id' => $session->getKey(),
            'email' => $session->user?->email
                ?? fake()->safeEmail(),
        ]);
    }

    /**
     * Create a history entry without a known user.
     *
     * This represents an authentication attempt using an
     * unknown email address.
     */
    public function unknownUser(): static
    {
        return $this->state([
            'user_id' => null,
            'authentication_session_id' => null,
            'event' => 'failed',
            'reason' => 'unknown_user',
        ]);
    }
}