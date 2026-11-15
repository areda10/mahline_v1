<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Countries\Models\Country;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Localization\Models\Address;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
final class AddressFactory extends Factory
{
    protected $model = Address::class;

    public function definition(): array
    {
        return [
            'number' => fake()->optional()->buildingNumber(),
            'street' => fake()->streetAddress(),
            'floor' => fake()->optional()->secondaryAddress(),
            'address_line_1' => fake()->streetAddress(),
            'address_line_2' => fake()->optional()->secondaryAddress(),
            'postal_code' => fake()->optional()->postcode(),
            'city' => fake()->city(),
            'region' => fake()->optional()->state(),
            'state' => fake()->optional()->state(),
            'country_id' => Country::query()->firstOrCreate(
                ['code' => 'MA'],
                [
                    'iso3' => 'MAR',
                    'name' => 'Morocco',
                    'native_name' => 'المغرب',
                    'phone_code' => '+212',
                    'currency' => 'MAD',
                    'locale' => 'fr',
                    'is_active' => true,
                ],
            )->getKey(),
            'user_id' => User::factory(),
            'cooperative_id' => null,
            'latitude' => fake()->optional()->latitude(),
            'longitude' => fake()->optional()->longitude(),
        ];
    }
}
