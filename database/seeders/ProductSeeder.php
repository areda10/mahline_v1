<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Products\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

final class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'name' => 'Miel naturel',
                'description' => 'Miel naturel produit au Maroc.',
            ],
            [
                'name' => 'Amlou traditionnel',
                'description' => 'Préparation traditionnelle à base d’amandes.',
            ],
            [
                'name' => 'Confiture de figues',
                'description' => 'Confiture artisanale de figues.',
            ],
            [
                'name' => 'Amandes grillées',
                'description' => 'Amandes soigneusement sélectionnées.',
            ],
            [
                'name' => 'Safran du Maroc',
                'description' => 'Safran conditionné par la coopérative.',
            ],
        ];

        Cooperative::query()->each(
            function (Cooperative $cooperative) use ($products): void {
                foreach ($products as $data) {
                    $slug = Str::slug($data['name']);

                    Product::query()->firstOrCreate(
                        [
                            'cooperative_id' => $cooperative->getKey(),
                            'slug' => $slug,
                            'deleted_at' => null,
                        ],
                        [
                            'name' => $data['name'],
                            'description' => $data['description'],
                            'is_active' => true,
                        ],
                    );
                }
            },
        );
    }
}
