<?php

declare(strict_types=1);

namespace App\Domains\Categories\Services;

use App\Domains\Audit\Contracts\AuditWriter;
use App\Domains\Categories\Models\Category;
use App\Domains\Categories\Repositories\CategoryRepository;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Products\Models\Product;
use App\Shared\DTOs\AuditEntryDTO;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CategoryService
{
    public function __construct(
        private readonly CategoryRepository $repository,
        private readonly AuditWriter $audit,
    ) {}

    public function create(array $attributes, ?User $actor = null): Category
    {
        return $this->createGlobal($attributes, $actor);
    }

    public function createForCooperative(
        string $cooperativeId,
        array $attributes,
        ?User $actor = null,
    ): Category {
        return DB::transaction(function () use (
            $cooperativeId,
            $attributes,
            $actor,
        ): Category {
            $attributes['cooperative_id'] = $cooperativeId;

            return $this->createInScope($attributes, $cooperativeId, $actor);
        });
    }

    /**
     * Cette méthode doit être réservée à l'administration MAHLINE
     * via l'autorisation du contrôleur ou la Policy.
     */
    public function createGlobal(array $attributes, ?User $actor = null): Category
    {
        return DB::transaction(function () use ($attributes, $actor): Category {
            $attributes['cooperative_id'] = null;

            return $this->createInScope($attributes, null, $actor);
        });
    }

    private function createInScope(
        array $attributes,
        ?string $cooperativeId,
        ?User $actor = null,
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

        $category = $this->repository->create($attributes);

        if ($actor !== null) {
            $category->forceFill(['created_by' => (string) $actor->getKey()])->save();
        }

        $this->recordCategory($actor, $category, 'category.created', 'created',
            newValues: $this->snapshotCategory($category));

        return $category;
    }

    public function update(
        Category $category,
        array $attributes,
        ?User $actor = null,
    ): Category {
        return DB::transaction(function () use (
            $category,
            $attributes,
            $actor,
        ): Category {
            $category = Category::query()->whereKey($category->getKey())->lockForUpdate()->firstOrFail();
            $before = $this->snapshotCategory($category);

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

            $updated = $this->repository->update($category, $attributes);

            if ($actor !== null) {
                $updated->forceFill(['updated_by' => (string) $actor->getKey()])->save();
            }

            if ($before !== $this->snapshotCategory($updated)) {
                $this->recordCategory($actor, $updated, 'category.updated', 'updated',
                    oldValues: $before, newValues: $this->snapshotCategory($updated));
            }

            return $updated;
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

    public function delete(Category $category, ?User $actor = null): void
    {
        DB::transaction(function () use ($category, $actor): void {
            $lockedCategory = Category::query()
                ->whereKey($category->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedCategory->isGlobal()) {
                $this->deleteGlobalBranch($lockedCategory, $actor);

                return;
            }

            $cooperativeId = (string) $lockedCategory->cooperative_id;

            $this->deleteLocalBranch(
                $lockedCategory,
                $cooperativeId,
                $actor,
            );
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
        ?User $actor = null,
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

        $productIds = $this->detachCategoryProducts($categoryIds);

        foreach ($categories->reverse() as $item) {
            $before = $this->snapshotCategory($item);
            if ($actor !== null) {
                $item->deleted_by = (string) $actor->getKey();
                $item->save();
            }
            $item->delete();
            $this->recordCategory($actor, $item, 'category.deleted', 'deleted',
                oldValues: $before, newValues: ['deleted' => true]);
        }

        $this->deactivateProductsWithoutActiveCategories($productIds, $actor);
    }

    /**
     * Supprime une branche globale sans supprimer les catégories locales.
     *
     * Les enfants globaux sont supprimés récursivement.
     * Les enfants locaux directs de chaque catégorie globale supprimée
     * sont conservés et deviennent des racines.
     */
    private function deleteGlobalBranch(Category $category, ?User $actor = null): void
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

        // Préserver les catégories locales et leurs sous-arbres.
        $localChildren = Category::query()
            ->whereIn('parent_id', $globalIds)
            ->whereNotNull('cooperative_id')
            ->lockForUpdate()
            ->get();

        foreach ($localChildren as $localChild) {
            $before = $this->snapshotCategory($localChild);
            $localChild->parent_id = null;
            if ($actor !== null) {
                $localChild->updated_by = (string) $actor->getKey();
            }
            $localChild->save();
            $this->recordCategory($actor, $localChild, 'category.parent_detached', 'parent_detached',
                oldValues: $before, newValues: $this->snapshotCategory($localChild));
        }

        $productIds = $this->detachCategoryProducts($globalIds);

        foreach ($globalCategories->reverse() as $item) {
            $before = $this->snapshotCategory($item);
            if ($actor !== null) {
                $item->deleted_by = (string) $actor->getKey();
                $item->save();
            }
            $item->delete();
            $this->recordCategory($actor, $item, 'category.deleted', 'deleted',
                oldValues: $before, newValues: ['deleted' => true]);
        }

        $this->deactivateProductsWithoutActiveCategories($productIds, $actor);
    }

    /**
     * Collecte la catégorie et tous ses descendants.
     *
     * L'ordre de collecte est parent puis descendants.
     */
    private function collectDescendants(Category $category): Collection
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
        Collection &$result,
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
     * Détache les associations et retourne les identifiants
     * des produits qui étaient associés à ces catégories.
     *
     * @return array<int, string>
     */
    private function detachCategoryProducts(array $categoryIds): array
    {
        if ($categoryIds === []) {
            return [];
        }

        $productIds = DB::table('category_product')
            ->whereIn('category_id', $categoryIds)
            ->distinct()
            ->pluck('product_id')
            ->map(fn ($id): string => (string) $id)
            ->all();

        DB::table('category_product')
            ->whereIn('category_id', $categoryIds)
            ->delete();

        return $productIds;
    }

    /**
     * Désactive les produits concernés qui n'ont plus
     * aucune catégorie active après le détachement.
     */
    private function deactivateProductsWithoutActiveCategories(
        array $productIds,
        ?User $actor = null,
    ): void {
        if ($productIds === []) {
            return;
        }

        $products = Product::query()
            ->whereIn('id', $productIds)
            ->whereDoesntHave('categories', function ($query): void {
                $query->where('categories.is_active', true);
            })
            ->lockForUpdate()
            ->get();

        foreach ($products as $product) {
            if ($product->is_active) {
                $product->is_active = false;
                if ($actor !== null) {
                    $product->updated_by = (string) $actor->getKey();
                }
                $product->save();
                $this->recordProduct($actor, $product, 'product.deactivated_no_active_categories',
                    'deactivated', oldValues: ['is_active' => true], newValues: ['is_active' => false]);
            }
        }
    }

    /** @return array<string, mixed> */
    private function snapshotCategory(Category $category): array
    {
        return [
            'cooperative_id' => $category->cooperative_id === null ? null : (string) $category->cooperative_id,
            'parent_id' => $category->parent_id === null ? null : (string) $category->parent_id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'is_active' => (bool) $category->is_active,
            'sort_order' => (int) $category->sort_order,
        ];
    }

    private function recordCategory(
        ?User $actor,
        Category $category,
        string $eventKey,
        string $action,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): void {
        if ($actor === null) {
            return;
        }

        $this->audit->record(new AuditEntryDTO(
            eventKey: $eventKey,
            action: $action,
            actorType: User::class,
            actorId: (string) $actor->getKey(),
            cooperativeId: $category->cooperative_id === null ? null : (string) $category->cooperative_id,
            subjectType: Category::class,
            subjectId: (string) $category->getKey(),
            oldValues: $oldValues,
            newValues: $newValues,
        ));
    }

    private function recordProduct(
        ?User $actor,
        Product $product,
        string $eventKey,
        string $action,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): void {
        if ($actor === null) {
            return;
        }

        $this->audit->record(new AuditEntryDTO(
            eventKey: $eventKey,
            action: $action,
            actorType: User::class,
            actorId: (string) $actor->getKey(),
            cooperativeId: (string) $product->cooperative_id,
            subjectType: Product::class,
            subjectId: (string) $product->getKey(),
            oldValues: $oldValues,
            newValues: $newValues,
        ));
    }
}
