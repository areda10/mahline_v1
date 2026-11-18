<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Products\Models\ProductUnit;
use Illuminate\Database\Seeder;

final class ProductUnitSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['code' => 'kg', 'label' => 'Kilogramme (kg)', 'dimension' => 'mass', 'sort_order' => 10],
            ['code' => 'g', 'label' => 'Gramme (g)', 'dimension' => 'mass', 'sort_order' => 20],
            ['code' => 'l', 'label' => 'Litre (L)', 'dimension' => 'volume', 'sort_order' => 30],
            ['code' => 'ml', 'label' => 'Millilitre (ml)', 'dimension' => 'volume', 'sort_order' => 40],
            ['code' => 'u', 'label' => 'Unité / pièce (u)', 'dimension' => 'count', 'sort_order' => 50],
        ];

        foreach ($units as $unit) {
            ProductUnit::query()->updateOrCreate(
                ['code' => $unit['code']],
                $unit + ['is_active' => true],
            );
        }
    }
}
