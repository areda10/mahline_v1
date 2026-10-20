<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Categories\Models;

use App\Core\Foundation\Models\BaseModel;
use App\Domains\Categories\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_uses_base_model_and_ulid_primary_key(): void
    {
        $category = $this->createCategory();

        $this->assertInstanceOf(BaseModel::class, $category);
        $this->assertSame('categories', $category->getTable());
        $this->assertSame('string', $category->getKeyType());
        $this->assertFalse($category->getIncrementing());
        $this->assertNotEmpty($category->getKey());
        $this->assertMatchesRegularExpression(
            '/^[0-9A-HJKMNP-TV-Z]{26}$/i',
            (string) $category->getKey(),
        );
    }

    public function test_category_casts_boolean_and_integer_attributes(): void
    {
        $category = $this->createCategory([
            'is_active' => 1,
            'sort_order' => '25',
        ]);

        $this->assertIsBool($category->is_active);
        $this->assertTrue($category->is_active);
        $this->assertIsInt($category->sort_order);
        $this->assertSame(25, $category->sort_order);
    }

    public function test_category_belongs_to_parent(): void
    {
        $parent = $this->createCategory([
            'name' => 'Alimentation',
            'slug' => 'alimentation',
        ]);

        $child = $this->createCategory([
            'name' => 'Miels',
            'slug' => 'miels',
            'parent_id' => $parent->getKey(),
        ]);

        $this->assertTrue($child->parent->is($parent));
    }

    public function test_category_has_children(): void
    {
        $parent = $this->createCategory([
            'name' => 'Alimentation',
            'slug' => 'alimentation',
        ]);

        $child = $this->createCategory([
            'name' => 'Fruits secs',
            'slug' => 'fruits-secs',
            'parent_id' => $parent->getKey(),
        ]);

        $this->assertTrue(
            $parent->children->contains(
                fn (Category $item): bool => $item->is($child),
            ),
        );
    }

    public function test_category_can_be_soft_deleted(): void
    {
        $category = $this->createCategory();

        $category->delete();

        $this->assertSoftDeleted('categories', [
            'id' => $category->getKey(),
        ]);

        $this->assertNull(
            Category::query()->find($category->getKey()),
        );
    }

    public function test_category_hides_soft_deleted_children_from_default_relation(): void
    {
        $parent = $this->createCategory([
            'name' => 'Alimentation',
            'slug' => 'alimentation',
        ]);

        $child = $this->createCategory([
            'name' => 'Miels',
            'slug' => 'miels',
            'parent_id' => $parent->getKey(),
        ]);

        $child->delete();

        $this->assertCount(0, $parent->children()->get());
    }

    private function createCategory(array $attributes = []): Category
    {
        return Category::query()->create([
            'name' => 'Catégorie test',
            'slug' => 'categorie-test',
            ...$attributes,
        ]);
    }
}