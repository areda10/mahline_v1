<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Catalog\Models\Catalog;
use App\Domains\Cooperatives\Models\Cooperative;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Catalog>
 */
final class CatalogFactory extends Factory
{
    protected $model = Catalog::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'cooperative_id' => Cooperative::factory(),
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state([
            'is_active' => false,
        ]);
    }
}
