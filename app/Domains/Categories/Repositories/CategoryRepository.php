<?php

declare(strict_types=1);

namespace App\Domains\Categories\Repositories;

use App\Domains\Categories\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class CategoryRepository
{
    public function paginateAvailableFor(
        string $cooperativeId,
        int $perPage = 20,
    ): LengthAwarePaginator {
        return Category::query()
            ->availableFor($cooperativeId)
            ->with(['parent', 'children'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function paginateGlobal(int $perPage = 20): LengthAwarePaginator
    {
        return Category::query()
            ->global()
            ->with(['parent', 'children'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function findAvailableForOrFail(
        string $categoryId,
        string $cooperativeId,
    ): Category {
        return Category::query()
            ->availableFor($cooperativeId)
            ->with(['parent', 'children'])
            ->findOrFail($categoryId);
    }

    public function create(array $attributes): Category
    {
        return Category::query()->create($attributes);
    }

    public function update(
        Category $category,
        array $attributes,
    ): Category {
        $category->update($attributes);

        return $category->refresh();
    }

    public function delete(Category $category): bool
    {
        return (bool) $category->delete();
    }
}