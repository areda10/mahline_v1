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

            $this->assertCategoriesBelongToCooperative(
                $categoryIds,
                $cooperativeId,
            );

            $this->assertCatalogsBelongToCooperative(
                $catalogIds,
                $cooperativeId,
            );

            $name = trim((string) ($attributes['name'] ?? ''));

            if ($name === '') {
                throw ValidationException::withMessages([
                    'name' => ['Le nom du produit est obligatoire.'],
                ]);
            }

            $slug = $this->makeAvailableSlug(
                $name,
                $cooperativeId,
            );

            $product = $this->repository->create([
                'cooperative_id' => $cooperativeId,
                'name' => $name,
                'slug' => $slug,
                'description' => $attributes['description'] ?? null,
                'is_active' => $attributes['is_active'] ?? true,
            ]);

            $product->categories()->sync($categoryIds);
            $product->catalogs()->sync($catalogIds);

            return $product->load([
                'variants',
                'categories',
                'catalogs',
            ]);
        });
    }

    public function update(
        Product $product,
        array $attributes,
    ): Product {
        return DB::transaction(function () use (
            $product,
            $attributes,
        ): Product {
            $categoryIds = array_key_exists(
                'category_ids',
                $attributes,
            )
                ? $this->normalizeIds($attributes['category_ids'])
                : null;

            $catalogIds = array_key_exists(
                'catalog_ids',
                $attributes,
            )
                ? $this->normalizeIds($attributes['catalog_ids'])
                : null;

            if ($categoryIds !== null) {
                $this->assertCategoriesBelongToCooperative(
                    $categoryIds,
                    (string) $product->cooperative_id,
                );
            }

            if ($catalogIds !== null) {
                $this->assertCatalogsBelongToCooperative(
                    $catalogIds,
                    (string) $product->cooperative_id,
                );
            }

            $update = [];

            if (array_key_exists('name', $attributes)) {
                $name = trim((string) $attributes['name']);

                if ($name === '') {
                    throw ValidationException::withMessages([
                        'name' => ['Le nom du produit est obligatoire.'],
                    ]);
                }

                $update['name'] = $name;
            }

            if (array_key_exists('description', $attributes)) {
                $update['description'] = $attributes['description'];
            }

            if (array_key_exists('is_active', $attributes)) {
                $update['is_active'] = (bool) $attributes['is_active'];
            }

            if (array_key_exists('slug', $attributes)) {
                $requestedSlug = Str::slug(
                    (string) $attributes['slug'],
                );

                if ($requestedSlug === '') {
                    throw ValidationException::withMessages([
                        'slug' => ['Le slug fourni est invalide.'],
                    ]);
                }

                $update['slug'] = $this->makeAvailableSlug(
                    $requestedSlug,
                    (string) $product->cooperative_id,
                    $product,
                );
            } elseif (array_key_exists('name', $attributes)) {
                $update['slug'] = $this->makeAvailableSlug(
                    $update['name'],
                    (string) $product->cooperative_id,
                    $product,
                );
            }

            if ($update !== []) {
                $this->repository->update($product, $update);
            }

            if ($categoryIds !== null) {
                $product->categories()->sync($categoryIds);
            }

            if ($catalogIds !== null) {
                $product->catalogs()->sync($catalogIds);
            }

            return $product->refresh()->load([
                'variants',
                'categories',
                'catalogs',
            ]);
        });
    }

    public function deactivate(Product $product): Product
    {
        $product->is_active = false;
        $product->save();

        return $product->refresh();
    }

    public function delete(Product $product): void
    {
        DB::transaction(function () use ($product): void {
            // Suppression logique uniquement.
            // Ne pas supprimer les variantes ni l'historique commercial.
            $this->repository->delete($product);
        });
    }

    private function assertCategoriesBelongToCooperative(
        array $categoryIds,
        string $cooperativeId,
    ): void {
        if ($categoryIds === []) {
            return;
        }

        $count = Category::query()
            ->where('cooperative_id', $cooperativeId)
            ->whereIn('id', $categoryIds)
            ->where('is_active', true)
            ->count();

        if ($count !== count($categoryIds)) {
            throw ValidationException::withMessages([
                'category_ids' => [
                    'Une ou plusieurs catégories sont invalides, inactives ou appartiennent à une autre coopérative.',
                ],
            ]);
        }
    }

    private function assertCatalogsBelongToCooperative(
        array $catalogIds,
        string $cooperativeId,
    ): void {
        if ($catalogIds === []) {
            return;
        }

        $count = Catalog::query()
            ->where('cooperative_id', $cooperativeId)
            ->whereIn('id', $catalogIds)
            ->count();

        if ($count !== count($catalogIds)) {
            throw ValidationException::withMessages([
                'catalog_ids' => [
                    'Un ou plusieurs catalogues sont invalides ou appartiennent à une autre coopérative.',
                ],
            ]);
        }
    }

    private function makeAvailableSlug(
        string $value,
        string $cooperativeId,
        ?Product $except = null,
    ): string {
        $base = Str::slug($value);

        if ($base === '') {
            throw ValidationException::withMessages([
                'slug' => ['Impossible de générer un slug valide.'],
            ]);
        }

        $slug = $base;
        $suffix = 2;

        while (true) {
            $query = Product::withTrashed()
                ->where('cooperative_id', $cooperativeId)
                ->where('slug', $slug)
                ->whereNull('deleted_at');

            if ($except !== null) {
                $query->whereKeyNot($except->getKey());
            }

            if (! $query->exists()) {
                return $slug;
            }

            $slug = $base.'-'.$suffix;
            $suffix++;
        }
    }

    private function normalizeIds(mixed $ids): array
    {
        if (! is_array($ids)) {
            throw ValidationException::withMessages([
                'associations' => [
                    'Les identifiants associés doivent être fournis sous forme de liste.',
                ],
            ]);
        }

        $normalized = array_values(array_unique(array_map(
            static fn (mixed $id): string => (string) $id,
            $ids,
        )));

        foreach ($normalized as $id) {
            if ($id === '') {
                throw ValidationException::withMessages([
                    'associations' => [
                        'Un identifiant associé ne peut pas être vide.',
                    ],
                ]);
            }
        }

        return $normalized;
    }
}