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
            // Verrouille la catégorie de départ pendant l'opération.
            $lockedCategory = Category::query()
                ->whereKey($category->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $categoryId = (string) $lockedCategory->getKey();

            if ($lockedCategory->isGlobal()) {
                $this->deleteGlobalBranch($lockedCategory);

                return;
            }

            $this->deleteLocalBranch($lockedCategory, $categoryId);
        });
    }

    /**
     * Supprime logiquement une branche locale.
     *
     * Une catégorie locale ne peut avoir pour descendants que
     * des catégories de la même coopérative.
     */
    private function deleteLocalBranch(
        Category $category,
        string $cooperativeId,
    ): void {
        $categories = $this->collectDescendants($category);

        foreach ($categories as $descendant) {
            if (
                $descendant->cooperative_id === null
                || (string) $descendant->cooperative_id !== $cooperativeId
            ) {
                throw ValidationException::withMessages([
                    'category' => [
                        'La branche contient une catégorie hors du périmètre autorisé.',
                    ],
                ]);
            }
        }

        $categoryIds = $categories
            ->map(fn (Category $item): string => (string) $item->getKey())
            ->all();

        $this->detachCategoryProducts($categoryIds);

        // Suppression logique des descendants, puis de la racine.
        foreach ($categories->reverse() as $item) {
            $item->delete();
        }

        $category->delete();
    }

    /**
     * Supprime une branche globale sans supprimer les catégories locales.
     *
     * Les enfants globaux sont supprimés récursivement.
     * Les enfants locaux directs de chaque catégorie globale supprimée
     * sont conservés et deviennent des racines.
     */
    private function deleteGlobalBranch(Category $category): void
    {
        $globalCategories = collect();
        $visited = [];

        $this->collectGlobalDescendants(
            $category,
            $globalCategories,
            $visited,
        );

        $globalIds = $globalCategories
            ->map(fn (Category $item): string => (string) $item->getKey())
            ->all();

        // Les catégories locales directement rattachées à une catégorie
        // globale de la branche sont conservées et deviennent racines.
        Category::query()
            ->whereIn('parent_id', $globalIds)
            ->whereNotNull('cooperative_id')
            ->update([
                'parent_id' => null,
                'updated_at' => now(),
            ]);

        // Le SoftDeletes ne supprime pas les associations de la table pivot.
        $this->detachCategoryProducts($globalIds);

        // Suppression logique des catégories globales de la branche.
        foreach ($globalCategories->reverse() as $item) {
            $item->delete();
        }
    }

    /**
     * Collecte la catégorie et tous ses descendants.
     *
     * L'ordre de collecte est parent puis descendants.
     */
    private function collectDescendants(Category $category): \Illuminate\Support\Collection
    {
        $result = collect();
        $visited = [];

        $walk = function (Category $current) use (
            &$walk,
            &$result,
            &$visited,
        ): void {
            $id = (string) $current->getKey();

            if (isset($visited[$id])) {
                throw ValidationException::withMessages([
                    'category' => [
                        'Un cycle a été détecté dans la hiérarchie des catégories.',
                    ],
                ]);
            }

            $visited[$id] = true;
            $result->push($current);

            $children = Category::query()
                ->where('parent_id', $id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($children as $child) {
                $walk($child);
            }
        };

        $walk($category);

        return $result;
    }

    /**
     * Parcourt uniquement la branche globale.
     *
     * Lorsqu'un enfant local est rencontré, il n'est pas ajouté
     * à la branche à supprimer. Il sera détaché lors de l'opération.
     */
    private function collectGlobalDescendants(
        Category $category,
        \Illuminate\Support\Collection &$result,
        array &$visited,
    ): void {
        $id = (string) $category->getKey();

        if (isset($visited[$id])) {
            throw ValidationException::withMessages([
                'category' => [
                    'Un cycle a été détecté dans la hiérarchie des catégories.',
                ],
            ]);
        }

        if (! $category->isGlobal()) {
            throw ValidationException::withMessages([
                'category' => [
                    'Une branche globale ne peut contenir que des catégories globales.',
                ],
            ]);
        }

        $visited[$id] = true;
        $result->push($category);

        $children = Category::query()
            ->where('parent_id', $id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($children as $child) {
            if ($child->isGlobal()) {
                $this->collectGlobalDescendants(
                    $child,
                    $result,
                    $visited,
                );
            }
        }
    }

    /**
     * Détache les produits des catégories supprimées.
     *
     * Aucun produit ni aucune variante n'est supprimé physiquement.
     */
    private function detachCategoryProducts(array $categoryIds): void
    {
        if ($categoryIds === []) {
            return;
        }

        DB::table('category_product')
            ->whereIn('category_id', $categoryIds)
            ->delete();
    }
}