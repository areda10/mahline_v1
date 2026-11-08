<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Countries\Models\Country;
use Illuminate\Database\Seeder;

final class CountrySeeder extends Seeder
{
    public function run(): void
    {
        Country::query()->updateOrCreate(
            ['code' => 'MA'],
            [
                'iso3' => 'MAR',
                'name' => 'Maroc',
                'native_name' => 'المغرب',
                'phone_code' => '+212',
                'currency' => 'MAD',
                'locale' => 'fr',
                'is_active' => true,
            ],
        );
    }
}
