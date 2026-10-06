<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Countries\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Country>
 */
final class CountryFactory extends Factory
{
    protected $model = Country::class;

    private const DEFAULT_COUNTRY = [
        'code' => 'MA',
        'iso3' => 'MAR',
        'name' => 'Morocco',
        'native_name' => 'المغرب',
        'phone_code' => '+212',
        'currency' => 'MAD',
        'locale' => 'fr',
    ];

    public function definition(): array
    {
        return [
            ...self::DEFAULT_COUNTRY,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}