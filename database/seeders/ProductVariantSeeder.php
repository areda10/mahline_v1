<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Products\Models\Product;
use App\Domains\Products\Models\ProductVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

final class ProductVariantSeeder extends Seeder
{
    public function run(): void
    {
        Product::query()
            ->where('is_active', true)
            ->each(function (Product $product): void {
                $variants = [
                    [
                        'name' => 'Pot en verre 250 g',
                        'attributes' => [
                            'weight' => '250 g',
                            'packaging' => 'glass',
                        ],
                    ],
                    [
                        'name' => 'Pot en verre 500 g',
                        'attributes' => [
                            'weight' => '500 g',
                            'packaging' => 'glass',
                        ],
                    ],
                ];

                foreach ($variants as $data) {
                    $normalized = Str::lower(
                        preg_replace(
                            '/\s+/',
                            ' ',
                            trim(Str::ascii($data['name'])),
                        ) ?? $data['name'],
                    );

                    ProductVariant::query()->firstOrCreate(
                        [
                            'product_id' => $product->getKey(),
                            'normalized_label' => $normalized,
                            'deleted_at' => null,
                        ],
                        [
                            'cooperative_id' => $product->cooperative_id,
                            'name' => $data['name'],
                            'sku' => null,
                            'attributes' => $data['attributes'],
                            'is_active' => true,
                        ],
                    );
                }
            });
    }
}
