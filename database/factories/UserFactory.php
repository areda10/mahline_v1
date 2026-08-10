<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Core\Foundation\Enums\Identity\UserStatus;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
final class UserFactory extends Factory
{
    /**
     * The model associated with the factory.
     */
    protected $model = User::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $firstName = fake()->firstName();
        $lastName = fake()->lastName();

        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'display_name' => "{$firstName} {$lastName}",
            'email' => fake()->unique()->safeEmail(),
            'telephone' => fake()->numerify('+212 6########'),
            'password' => 'password',
            'status' => UserStatus::ACTIVE,
            'locale' => 'fr',
            'timezone' => 'UTC',
            'email_verified_at' => now(),
            'remember_token' => null,
        ];
    }
}