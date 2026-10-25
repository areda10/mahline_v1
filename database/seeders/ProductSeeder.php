<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Categories\Models\Category;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Products\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

final class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $category = Category::query()
            ->whereNull('cooperative_id')
            ->where('slug', 'miels-amlou-confitures')
            ->where('is_active', true)
            ->first();

        if ($category === null) {
            $this->command?->warn(
                'ProductSeeder ignoré : exécutez CategorySeeder en premier.'
            );

            return;
        }

        $cooperatives = Cooperative::query()->get();

        foreach ($cooperatives as $cooperative) {
            $products = [
                'Miel naturel',
                'Amlou traditionnel',
                'Confiture artisanale',
            ];

            foreach ($products as $name) {
                $slug = Str::slug($name);

                $product = Product::query()->firstOrCreate(
                    [
                        'cooperative_id' => $cooperative->getKey(),
                        'slug' => $slug,
                    ],
                    [
                        'name' => $name,
                        'description' => 'Produit de démonstration MAHLINE.',
                        'is_active' => true,
                    ],
                );

                // Évite de dupliquer l'association au prochain seeding.
                $product->categories()->syncWithoutDetaching([
                    $category->getKey(),
                ]);
            }
        }
    }
}