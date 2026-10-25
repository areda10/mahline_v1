<?php

declare(strict_types=1);

namespace App\Domains\Categories\Services;

use App\Domains\Categories\Models\Category;
use App\Domains\Categories\Repositories\CategoryRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CategoryService
{
    public function __construct(
        private readonly CategoryRepository $repository,
    ) {
    }

    public function create(array $attributes): Category
    {
        return $this->createGlobal($attributes);
    }

    public function createForCooperative(
        string $cooperativeId,
        array $attributes,
    ): Category {
        return DB::transaction(function () use (
            $cooperativeId,
            $attributes,
        ): Category {
            $attributes['cooperative_id'] = $cooperativeId;

            return $this->createInScope($attributes, $cooperativeId);
        });
    }

    /**
     * Cette méthode doit être réservée à l'administration MAHLINE
     * via l'autorisation du contrôleur ou la Policy.
     */
    public function createGlobal(array $attributes): Category
    {
        return DB::transaction(function () use ($attributes): Category {
            $attributes['cooperative_id'] = null;

            return $this->createInScope($attributes, null);
        });
    }

    private function createInScope(
        array $attributes,
        ?string $cooperativeId,
    ): Category {
        $name = trim((string) ($attributes['name'] ?? ''));

        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => ['Le nom de la catégorie est obligatoire.'],
            ]);
        }

        $parentId = isset($attributes['parent_id'])
            ? (string) $attributes['parent_id']
            : null;

        $this->validateParent($parentId, $cooperativeId);

        $attributes['name'] = $name;
        $attributes['slug'] = $this->generateUniqueSlug(
            $cooperativeId,
            (string) ($attributes['slug'] ?? $name),
        );
        $attributes['parent_id'] = $parentId;
        $attributes['is_active'] = (bool) ($attributes['is_active'] ?? true);
        $attributes['sort_order'] = (int) ($attributes['sort_order'] ?? 0);

        return $this->repository->create($attributes);
    }

    public function update(
        Category $category,
        array $attributes,
    ): Category {
        return DB::transaction(function () use (
            $category,
            $attributes,
        ): Category {
            // Le périmètre d'une catégorie ne change jamais via update.
            unset($attributes['cooperative_id']);

            $cooperativeId = $category->cooperative_id !== null
                ? (string) $category->cooperative_id
                : null;

            if (array_key_exists('parent_id', $attributes)) {
                $parentId = $attributes['parent_id'] !== null
                    ? (string) $attributes['parent_id']
                    : null;

                $this->validateParent($parentId, $cooperativeId, $category);
                $attributes['parent_id'] = $parentId;
            }

            if (array_key_exists('name', $attributes)) {
                $attributes['name'] = trim((string) $attributes['name']);

                if ($attributes['name'] === '') {
                    throw ValidationException::withMessages([
                        'name' => ['Le nom de la catégorie est obligatoire.'],
                    ]);
                }
            }

            if (
                array_key_exists('slug', $attributes)
                || array_key_exists('name', $attributes)
            ) {
                $source = (string) (
                    $attributes['slug']
                    ?? $attributes['name']
                    ?? $category->name
                );

                $attributes['slug'] = $this->generateUniqueSlug(
                    $cooperativeId,
                    $source,
                    (string) $category->getKey(),
                );
            }

            return $this->repository->update($category, $attributes);
        });
    }

    private function validateParent(
        ?string $parentId,
        ?string $cooperativeId,
        ?Category $category = null,
    ): void {
        if ($parentId === null) {
            return;
        }

        if (
            $category !== null
            && $parentId === (string) $category->getKey()
        ) {
            throw ValidationException::withMessages([
                'parent_id' => ['Une catégorie ne peut pas être son propre parent.'],
            ]);
        }

        // $parent = Category::query()->find($parentId);

        // if ($parent === null || ! $parent->is_active) {
        //     throw ValidationException::withMessages([
        //         'parent_id' => ['La catégorie parente est inexistante ou inactive.'],
        //     ]);
        // }

        $parent = Category::query()->findOrFail($parentId);

        if (! $parent->is_active) {
            throw ValidationException::withMessages([
                'parent_id' => ['La catégorie parente est inactive.'],
            ]);
        }

        // Une catégorie globale peut être parente d'une catégorie globale
        // ou d'une catégorie locale.
        // Une catégorie locale ne peut être parente que d'une catégorie
        // de la même coopérative.
        if (
            $parent->cooperative_id !== null
            && (
                $cooperativeId === null
                || (string) $parent->cooperative_id !== $cooperativeId
            )
        ) {
            throw ValidationException::withMessages([
                'parent_id' => [
                    'La catégorie parente appartient à une autre coopérative.',
                ],
            ]);
        }

        // Empêche les cycles lors du déplacement d'une catégorie.
        $visited = [];
        $current = $parent;

        while ($current !== null) {
            $currentId = (string) $current->getKey();

            if (isset($visited[$currentId])) {
                throw ValidationException::withMessages([
                    'parent_id' => ['La hiérarchie existante contient un cycle.'],
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

    private function generateUniqueSlug(
        ?string $cooperativeId,
        string $source,
        ?string $ignoreId = null,
    ): string {
        $base = Str::slug($source) ?: 'categorie';
        $slug = $base;
        $suffix = 2;

        do {
            $query = Category::query()
                ->where('slug', $slug)
                ->whereNull('deleted_at');

            if ($cooperativeId === null) {
                $query->whereNull('cooperative_id');
            } else {
                $query->where('cooperative_id', $cooperativeId);
            }

            if ($ignoreId !== null) {
                $query->where('id', '!=', $ignoreId);
            }

            $exists = $query->exists();

            if ($exists) {
                $slug = $base.'-'.$suffix;
                $suffix++;
            }
        } while ($exists);

        return $slug;
    }

    public function delete(Category $category): void
    {
        DB::transaction(function () use ($category): void {
            if ($category->children()->exists()) {
                throw ValidationException::withMessages([
                    'category' => [
                        'Impossible de supprimer une catégorie qui possède des sous-catégories.',
                    ],
                ]);
            }

            $this->repository->delete($category);
        });
    }
}