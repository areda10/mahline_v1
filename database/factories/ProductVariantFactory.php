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
        $label = fake()->randomElement([
            'Pot en verre 250 g',
            'Pot en verre 500 g',
            'Bouteille 300 ml',
            'Sachet 1 kg',
        ]);

        return [
            'product_id' => Product::factory(),
            'cooperative_id' => fn (array $attributes): string =>
                (string) Product::query()
                    ->findOrFail($attributes['product_id'])
                    ->cooperative_id,
            'name' => $label,
            'normalized_label' => Str::lower(
                preg_replace('/\s+/', ' ', trim(Str::ascii($label))) ?? $label
            ),
            'sku' => fake()->boolean(70)
                    ? fake()->unique()->bothify('SKU-####-????')
                    : null,
            'attributes' => [
                'packaging' => fake()->randomElement([
                    'glass',
                    'plastic',
                    'pouch',
                ]),
            ],
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
