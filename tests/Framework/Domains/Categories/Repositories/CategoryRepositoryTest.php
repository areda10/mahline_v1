<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Categories\Repositories;

use App\Domains\Categories\Models\Category;
use App\Domains\Categories\Repositories\CategoryRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Tests\TestCase;

final class CategoryRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private CategoryRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app(CategoryRepository::class);
    }

    public function test_repository_creates_category(): void
    {
        $category = $this->repository->create([
            'name' => 'Épices',
            'slug' => 'epices',
        ]);

        $this->assertDatabaseHas('categories', [
            'id' => $category->getKey(),
            'name' => 'Épices',
            'slug' => 'epices',
        ]);
    }

    public function test_repository_finds_category_by_id(): void
    {
        $category = $this->createCategory();

        $found = $this->repository->findOrFail(
            (string) $category->getKey(),
        );

        $this->assertTrue($found->is($category));
    }

    public function test_repository_throws_when_category_does_not_exist(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repository->findOrFail(
            '01J00000000000000000000000',
        );
    }

    public function test_repository_updates_category(): void
    {
        $category = $this->createCategory();

        $updated = $this->repository->update($category, [
            'name' => 'Bien-être',
            'slug' => 'bien-etre',
        ]);

        $this->assertSame('Bien-être', $updated->name);
        $this->assertSame('bien-etre', $updated->slug);

        $this->assertDatabaseHas('categories', [
            'id' => $category->getKey(),
            'name' => 'Bien-être',
        ]);
    }

    public function test_repository_paginates_categories_with_parent_relation(): void
    {
        $parent = $this->createCategory([
            'name' => 'Alimentation',
            'slug' => 'alimentation',
        ]);

        $this->createCategory([
            'name' => 'Miels',
            'slug' => 'miels',
            'parent_id' => $parent->getKey(),
        ]);

        $page = $this->repository->paginate(10);

        $this->assertSame(2, $page->total());
        $this->assertTrue(
            $page->getCollection()->every(
                fn (Category $category): bool =>
                    $category->relationLoaded('parent'),
            ),
        );
    }

    public function test_repository_deletes_category_logically(): void
    {
        $category = $this->createCategory();

        $this->assertTrue($this->repository->delete($category));

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