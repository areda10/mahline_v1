<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Categories\Models\Category;
use App\Domains\Cooperatives\Models\Cooperative;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
final class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'cooperative_id' => Cooperative::factory(),
            'parent_id' => null,
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function global(): static
    {
        return $this->state(fn (): array => [
            'cooperative_id' => null,
            'parent_id' => null,
        ]);
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

    public function childOf(Category $parent): static
    {
        return $this->state(fn (): array => [
            'parent_id' => $parent->getKey(),
        ]);
    }
}