<?php

declare(strict_types=1);

namespace App\Domains\Categories\Services;

use App\Core\Foundation\Services\BaseService;
use App\Domains\Categories\Models\Category;
use App\Domains\Categories\Repositories\CategoryRepository;
use Illuminate\Validation\ValidationException;

final class CategoryService extends BaseService
{
    public function __construct(
        private readonly CategoryRepository $categories,
    ) {
    }

    public function create(array $data): Category
    {
        return $this->categories->create($data);
    }

    public function update(
        Category $category,
        array $data,
    ): Category {
        if (array_key_exists('parent_id', $data)) {
            $this->assertNoParentCycle(
                $category,
                $data['parent_id'],
            );
        }

        return $this->categories->update(
            $category,
            $data,
        );
    }

    public function delete(Category $category): void
    {
        if ($category->children()->exists()) {
            throw ValidationException::withMessages([
                'category' => [
                    'Une catégorie ayant des sous-catégories ne peut pas être supprimée.',
                ],
            ]);
        }

        $this->categories->delete($category);
    }

    private function assertNoParentCycle(
        Category $category,
        mixed $parentId,
    ): void {
        if ($parentId === null) {
            return;
        }

        $currentId = (string) $parentId;
        $categoryId = (string) $category->getKey();
        $visited = [];

        while ($currentId !== '') {
            if ($currentId === $categoryId) {
                throw ValidationException::withMessages([
                    'parent_id' => [
                        'Une catégorie ne peut pas devenir son propre parent ou celui de ses ancêtres.',
                    ],
                ]);
            }

            if (isset($visited[$currentId])) {
                throw ValidationException::withMessages([
                    'parent_id' => [
                        'Une boucle existe déjà dans la hiérarchie des catégories.',
                    ],
                ]);
            }

            $visited[$currentId] = true;

            $nextId = Category::query()
                ->whereKey($currentId)
                ->value('parent_id');

            if ($nextId === null) {
                break;
            }

            $currentId = (string) $nextId;
        }
    }
}