<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Products\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
final class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'cooperative_id' => Cooperative::factory(),
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'description' => fake()->optional()->paragraph(),
            'is_active' => true,
        ];
    }

    public function forCooperative(Cooperative|string $cooperative): static
    {
        return $this->state(fn (): array => [
            'cooperative_id' => $cooperative instanceof Cooperative
                ? $cooperative->getKey()
                : $cooperative,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}