<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Categories\Services;

use App\Domains\Categories\Models\Category;
use App\Domains\Categories\Services\CategoryService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class CategoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private CategoryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(CategoryService::class);
    }

    public function test_service_creates_category(): void
    {
        $category = $this->service->create([
            'name' => 'Bien-être',
            'slug' => 'bien-etre',
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $this->assertDatabaseHas('categories', [
            'id' => $category->getKey(),
            'slug' => 'bien-etre',
        ]);
    }

    public function test_service_creates_child_category(): void
    {
        $parent = $this->createCategory([
            'name' => 'Alimentation',
            'slug' => 'alimentation',
        ]);

        $child = $this->service->create([
            'name' => 'Miels',
            'slug' => 'miels',
            'parent_id' => $parent->getKey(),
        ]);

        $this->assertSame(
            $parent->getKey(),
            $child->parent_id,
        );
    }

    public function test_service_rejects_nonexistent_parent(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->service->create([
            'name' => 'Miels',
            'slug' => 'miels',
            'parent_id' => '01J00000000000000000000000',
        ]);
    }

    public function test_service_rejects_category_as_its_own_parent(): void
    {
        $category = $this->createCategory();

        try {
            $this->service->update($category, [
                'parent_id' => $category->getKey(),
            ]);

            $this->fail(
                'Une catégorie ne doit pas être son propre parent.',
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'parent_id',
                $exception->errors(),
            );
        }
    }

    public function test_service_rejects_parent_that_is_a_descendant(): void
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

        try {
            $this->service->update($parent, [
                'parent_id' => $child->getKey(),
            ]);

            $this->fail(
                'Le déplacement doit être refusé pour éviter un cycle.',
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'parent_id',
                $exception->errors(),
            );
        }
    }

    public function test_service_updates_category(): void
    {
        $category = $this->createCategory();

        $updated = $this->service->update($category, [
            'name' => 'Épices',
            'slug' => 'epices',
        ]);

        $this->assertSame('Épices', $updated->name);
        $this->assertSame('epices', $updated->slug);
    }

    public function test_service_deletes_global_parent_and_global_children(): void
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

        $this->assertNull($parent->cooperative_id);
        $this->assertNull($child->cooperative_id);

        $this->service->delete($parent);

        $this->assertSoftDeleted('categories', [
            'id' => $parent->getKey(),
        ]);

        $this->assertSoftDeleted('categories', [
            'id' => $child->getKey(),
        ]);
    }

    public function test_service_soft_deletes_category_without_children(): void
    {
        $category = $this->createCategory();

        $this->service->delete($category);

        $this->assertSoftDeleted('categories', [
            'id' => $category->getKey(),
        ]);
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