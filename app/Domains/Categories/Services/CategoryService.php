<?php

declare(strict_types=1);

namespace App\Domains\Categories\Services;

use App\Domains\Categories\Models\Category;
use App\Domains\Categories\Repositories\CategoryRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CategoryService
{
    public function __construct(
        private readonly CategoryRepository $repository,
    ) {
    }

    public function create(array $attributes): Category
    {
        return DB::transaction(function () use ($attributes): Category {
            $this->validateParent(
                parentId: $attributes['parent_id'] ?? null,
            );

            return $this->repository->create($attributes);
        });
    }

    public function update(
        Category $category,
        array $attributes,
    ): Category {
        return DB::transaction(function () use (
            $category,
            $attributes,
        ): Category {
            if (array_key_exists('parent_id', $attributes)) {
                $this->validateParent(
                    parentId: $attributes['parent_id'],
                    category: $category,
                );
            }

            return $this->repository->update(
                $category,
                $attributes,
            );
        });
    }

    public function delete(Category $category): void
    {
        if ($this->repository->hasChildren($category)) {
            throw ValidationException::withMessages([
                'category' => [
                    'Impossible de supprimer une catégorie qui possède des sous-catégories.',
                ],
            ]);
        }

        $this->repository->delete($category);
    }

    private function validateParent(
        ?string $parentId,
        ?Category $category = null,
    ): void {
        if ($parentId === null) {
            return;
        }

        if ($category !== null && $parentId === $category->getKey()) {
            throw ValidationException::withMessages([
                'parent_id' => [
                    'Une catégorie ne peut pas être son propre parent.',
                ],
            ]);
        }

        $parent = Category::query()->find($parentId);

        if ($parent === null) {
            throw (new ModelNotFoundException())
                ->setModel(Category::class, [$parentId]);
        }

        $visited = [];
        $current = $parent;

        while ($current !== null) {
            $currentId = (string) $current->getKey();

            if (isset($visited[$currentId])) {
                throw ValidationException::withMessages([
                    'parent_id' => [
                        'La hiérarchie actuelle contient un cycle.',
                    ],
                ]);
            }

            $visited[$currentId] = true;

            if (
                $category !== null
                && $currentId === (string) $category->getKey()
            ) {
                throw ValidationException::withMessages([
                    'parent_id' => [
                        'Une catégorie ne peut pas devenir son propre descendant.',
                    ],
                ]);
            }

            $current = $current->parent;
        }
    }
}