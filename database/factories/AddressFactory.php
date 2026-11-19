<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Localization\Models\Country;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Localization\Enums\MoroccanRegion;
use App\Domains\Localization\Models\Address;
use Illuminate\Database\Eloquent\Factories\Factory;

final class AddressFactory extends Factory
{
    protected $model = Address::class;

    public function definition(): array
    {
        return [
            'number' => fake()->optional()->buildingNumber(),
            'street' => fake()->streetAddress(),
            'floor' => fake()->optional()->secondaryAddress(),
            'postal_code' => fake()->optional()->postcode(),
            'city' => fake()->city(),
            'region' => fake()->optional()->randomElement(MoroccanRegion::values()),
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
            'user_id' => null,
            'cooperative_id' => null,
            'is_primary' => true,
            'latitude' => fake()->optional()->latitude(),
            'longitude' => fake()->optional()->longitude(),
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (): array => [
            'user_id' => $user->getKey(),
            'cooperative_id' => null,
        ]);
    }

    public function forCooperative(Cooperative $cooperative): static
    {
        return $this->state(fn (): array => [
            'user_id' => null,
            'cooperative_id' => $cooperative->getKey(),
        ]);
    }
}
