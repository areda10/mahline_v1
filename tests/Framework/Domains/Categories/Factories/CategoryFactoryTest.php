<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Categories\Factories;

use App\Domains\Categories\Models\Category;
use App\Domains\Cooperatives\Models\Cooperative;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CategoryFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_global_category(): void
    {
        $category = Category::factory()->global()->create();

        $this->assertNull($category->cooperative_id);
        $this->assertNull($category->parent_id);
        $this->assertDatabaseHas('categories', [
            'id' => $category->getKey(),
            'cooperative_id' => null,
        ]);
    }

    public function test_it_creates_a_category_for_a_cooperative(): void
    {
        $cooperative = Cooperative::factory()->create();

        $category = Category::factory()
            ->forCooperative($cooperative)
            ->create();

        $this->assertSame(
            $cooperative->getKey(),
            $category->cooperative_id,
        );
    }

    public function test_it_can_create_a_child_category(): void
    {
        $parent = Category::factory()->global()->create();

        $child = Category::factory()
            ->childOf($parent)
            ->create();

        $this->assertSame($parent->getKey(), $child->parent_id);
        $this->assertNull($child->cooperative_id);
    }
}