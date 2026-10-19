<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Categories\Models\Category;
use Illuminate\Database\Seeder;

final class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Bien-être',
                'slug' => 'bien-etre',
                'sort_order' => 10,
            ],
            [
                'name' => 'Fruits secs',
                'slug' => 'fruits-secs',
                'sort_order' => 20,
            ],
            [
                'name' => 'Miels, amlou et confitures',
                'slug' => 'miels-amlou-confitures',
                'sort_order' => 30,
            ],
            [
                'name' => 'Épices',
                'slug' => 'epices',
                'sort_order' => 40,
            ],
        ];

        foreach ($categories as $attributes) {
            Category::query()->withTrashed()->updateOrCreate(
                ['slug' => $attributes['slug']],
                [
                    ...$attributes,
                    'is_active' => true,
                    'parent_id' => null,
                ],
            );
        }
    }
}