<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Products\Models\Product;
use App\Domains\Products\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductVariant>
 */
final class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        $label = fake()->unique()->words(2, true);

        return [
            'product_id' => Product::factory(),
            'label' => Str::title($label),
            'normalized_label' => Str::lower(trim($label)),
            'sku' => fake()->boolean(70)
                ? fake()->unique()->bothify('SKU-####-????')
                : null,
            'attributes' => [
                'format' => fake()->randomElement([
                    '250 g',
                    '500 g',
                    '1 kg',
                ]),
            ],
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