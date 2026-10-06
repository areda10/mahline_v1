<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Localization\Models\Address;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Address>
 */
final class AddressFactory extends Factory
{
    protected $model = Address::class;

    public function definition(): array
    {
        return [
            'address_line_1' => fake()->streetAddress(),
            'address_line_2' => fake()->optional()->secondaryAddress(),
            'postal_code' => fake()->optional()->postcode(),
            'city' => fake()->city(),
            'state' => fake()->optional()->state(),
            'country_id' => (string) Str::ulid(),
            'latitude' => fake()->optional()->latitude(),
            'longitude' => fake()->optional()->longitude(),
        ];
    }
}
