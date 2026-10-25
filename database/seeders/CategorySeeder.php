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
            'Bien-être' => 'bien-etre',
            'Fruits secs' => 'fruits-secs',
            'Miels, Amlou et Confitures' => 'miels-amlou-confitures',
            'Épices' => 'epices',
        ];

        foreach ($categories as $name => $slug) {
            Category::query()->firstOrCreate(
                [
                    'cooperative_id' => null,
                    'slug' => $slug,
                ],
                [
                    'parent_id' => null,
                    'name' => $name,
                    'description' => null,
                    'is_active' => true,
                    'sort_order' => 0,
                ],
            );
        }
    }
}