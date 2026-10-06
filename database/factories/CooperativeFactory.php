<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Cooperatives\Enums\CooperativeStatus;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Localization\Models\Address;
use Illuminate\Database\Eloquent\Factories\Factory;


/**
 * @extends Factory<Cooperative>
 */
final class CooperativeFactory extends Factory
{
    protected $model = Cooperative::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'rib' => fake()->numerify('########################'),
            'tax_id' => fake()->optional()->bothify('PAT-#####'),
            'address_id' => Address::factory(),
            'phone' => fake()->numerify('+2126########'),
            'email' => fake()->unique()->safeEmail(),
            'rc' => fake()->optional()->bothify('RC-#####'),
            'ice' => fake()->unique()->numerify('###############'),
            'status' => CooperativeStatus::PENDING,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => CooperativeStatus::ACTIVE,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => [
            'status' => CooperativeStatus::SUSPENDED,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'status' => CooperativeStatus::INACTIVE,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => [
            'status' => CooperativeStatus::ARCHIVED,
        ]);
    }
}