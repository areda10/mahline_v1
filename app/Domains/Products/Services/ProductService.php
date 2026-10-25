<?php

declare(strict_types=1);

namespace App\Domains\Products\Services;

use App\Domains\Catalog\Models\Catalog;
use App\Domains\Categories\Models\Category;
use App\Domains\Products\Models\Product;
use App\Domains\Products\Repositories\ProductRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ProductService
{
    public function __construct(
        private readonly ProductRepository $repository,
    ) {
    }

    public function create(
        string $cooperativeId,
        array $attributes,
    ): Product {
        return DB::transaction(function () use (
            $cooperativeId,
            $attributes,
        ): Product {
            $categoryIds = $this->normalizeIds(
                $attributes['category_ids'] ?? [],
            );

            $catalogIds = $this->normalizeIds(
                $attributes['catalog_ids'] ?? [],
            );

            $this->assertCategoriesAvailable($categoryIds, $cooperativeId);
            $this->assertCatalogsOwnedByCooperative($catalogIds, $cooperativeId);

            $name = trim((string) ($attributes['name'] ?? ''));

            if ($name === '') {
                throw ValidationException::withMessages([
                    'name' => ['Le nom du produit est obligatoire.'],
                ]);
            }

            $product = $this->repository->create([
                'cooperative_id' => $cooperativeId,
                'name' => $name,
                'slug' => $this->uniqueSlug($cooperativeId, $name),
                'description' => $attributes['description'] ?? null,
                'is_active' => (bool) ($attributes['is_active'] ?? true),
            ]);

            $product->categories()->sync($categoryIds);
            $product->catalogs()->sync($catalogIds);

            return $product->load(['categories', 'catalogs', 'variants']);
        });
    }

    public function update(
        Product $product,
        string $cooperativeId,
        array $attributes,
    ): Product {
        return DB::transaction(function () use (
            $product,
            $cooperativeId,
            $attributes,
        ): Product {
            if ((string) $product->cooperative_id !== $cooperativeId) {
                throw ValidationException::withMessages([
                    'product' => ['Ce produit ne relève pas de cette coopérative.'],
                ]);
            }

            $categoryIds = array_key_exists('category_ids', $attributes)
                ? $this->normalizeIds($attributes['category_ids'] ?? [])
                : null;

            $catalogIds = array_key_exists('catalog_ids', $attributes)
                ? $this->normalizeIds($attributes['catalog_ids'] ?? [])
                : null;

            if ($categoryIds !== null) {
                $this->assertCategoriesAvailable($categoryIds, $cooperativeId);
            }

            if ($catalogIds !== null) {
                $this->assertCatalogsOwnedByCooperative(
                    $catalogIds,
                    $cooperativeId,
                );
            }

            $changes = [];

            if (array_key_exists('name', $attributes)) {
                $name = trim((string) $attributes['name']);

                if ($name === '') {
                    throw ValidationException::withMessages([
                        'name' => ['Le nom du produit est obligatoire.'],
                    ]);
                }

                $changes['name'] = $name;
                $changes['slug'] = $this->uniqueSlug(
                    $cooperativeId,
                    $name,
                    (string) $product->getKey(),
                );
            }

            if (array_key_exists('description', $attributes)) {
                $changes['description'] = $attributes['description'];
            }

            if (array_key_exists('is_active', $attributes)) {
                $changes['is_active'] = (bool) $attributes['is_active'];
            }

            // Le propriétaire ne peut pas être changé via update.
            $this->repository->update($product, $changes);

            if ($categoryIds !== null) {
                $product->categories()->sync($categoryIds);
            }

            if ($catalogIds !== null) {
                $product->catalogs()->sync($catalogIds);
            }

            return $product->refresh()
                ->load(['categories', 'catalogs', 'variants']);
        });
    }

    private function assertCategoriesAvailable(
        array $categoryIds,
        string $cooperativeId,
    ): void {
        if ($categoryIds === []) {
            return;
        }

        $count = Category::query()
            ->whereIn('id', $categoryIds)
            ->where('is_active', true)
            ->where(function ($query) use ($cooperativeId): void {
                $query->whereNull('cooperative_id')
                    ->orWhere('cooperative_id', $cooperativeId);
            })
            ->count();

        if ($count !== count($categoryIds)) {
            throw ValidationException::withMessages([
                'category_ids' => [
                    'Une catégorie est inexistante, inactive ou appartient à une autre coopérative.',
                ],
            ]);
        }
    }

    private function assertCatalogsOwnedByCooperative(
        array $catalogIds,
        string $cooperativeId,
    ): void {
        if ($catalogIds === []) {
            return;
        }

        $count = Catalog::query()
            ->whereIn('id', $catalogIds)
            ->where('cooperative_id', $cooperativeId)
            ->where('is_active', true)
            ->count();

        if ($count !== count($catalogIds)) {
            throw ValidationException::withMessages([
                'catalog_ids' => [
                    'Un catalogue est inexistant, inactif ou appartient à une autre coopérative.',
                ],
            ]);
        }
    }

    private function normalizeIds(array $ids): array
    {
        return array_values(array_unique(array_map(
            static fn ($id): string => (string) $id,
            $ids,
        )));
    }

    private function uniqueSlug(
        string $cooperativeId,
        string $name,
        ?string $ignoreId = null,
    ): string {
        $base = Str::slug($name) ?: 'produit';
        $slug = $base;
        $suffix = 2;

        do {
            $query = Product::query()
                ->where('cooperative_id', $cooperativeId)
                ->where('slug', $slug);

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
}