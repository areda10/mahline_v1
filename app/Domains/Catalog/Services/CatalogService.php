<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Services;

use App\Domains\Catalog\Models\Catalog;
use App\Domains\Catalog\Repositories\CatalogRepository;
use App\Domains\Products\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CatalogService
{
    public function __construct(
        private readonly CatalogRepository $repository
    ) {
    }

    public function paginateForCooperative(
        string $cooperativeId,
        int $perPage = 20
    ): LengthAwarePaginator {
        if ($perPage < 1 || $perPage > 100) {
            throw ValidationException::withMessages([
                'per_page' => ['Le nombre d’éléments doit être compris entre 1 et 100.'],
            ]);
        }

        return $this->repository->paginateForCooperative(
            $cooperativeId,
            $perPage
        );
    }

    public function findForCooperativeOrFail(
        string $catalogId,
        string $cooperativeId
    ): Catalog {
        return $this->repository->findForCooperativeOrFail(
            $catalogId,
            $cooperativeId
        );
    }

    public function createForCooperative(
        string $cooperativeId,
        array $attributes
    ): Catalog {
        $name = trim((string) ($attributes['name'] ?? ''));

        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => ['Le nom du catalogue est obligatoire.'],
            ]);
        }

        return DB::transaction(function () use (
            $cooperativeId,
            $attributes,
            $name
        ): Catalog {
            $slug = $this->generateUniqueSlug(
                $cooperativeId,
                (string) ($attributes['slug'] ?? $name)
            );

            return $this->repository->create([
                'cooperative_id' => $cooperativeId,
                'name' => $name,
                'slug' => $slug,
                'description' => $attributes['description'] ?? null,
                'is_active' => $attributes['is_active'] ?? true,
            ]);
        });
    }

    public function update(
        Catalog $catalog,
        string $cooperativeId,
        array $attributes
    ): Catalog {
        if ($catalog->cooperative_id !== $cooperativeId) {
            throw (new ModelNotFoundException())
                ->setModel(Catalog::class, [$catalog->getKey()]);
        }

        return DB::transaction(function () use (
            $catalog,
            $cooperativeId,
            $attributes
        ): Catalog {
            $updates = [];

            if (array_key_exists('name', $attributes)) {
                $name = trim((string) $attributes['name']);

                if ($name === '') {
                    throw ValidationException::withMessages([
                        'name' => ['Le nom du catalogue est obligatoire.'],
                    ]);
                }

                $updates['name'] = $name;
            }

            if (array_key_exists('description', $attributes)) {
                $updates['description'] = $attributes['description'];
            }

            if (array_key_exists('is_active', $attributes)) {
                $updates['is_active'] = (bool) $attributes['is_active'];
            }

            if (array_key_exists('slug', $attributes)
                || array_key_exists('name', $attributes)) {
                $source = (string) (
                    $attributes['slug']
                    ?? $attributes['name']
                    ?? $catalog->slug
                );

                $updates['slug'] = $this->generateUniqueSlug(
                    $cooperativeId,
                    $source,
                    $catalog->getKey()
                );
            }

            return $this->repository->update($catalog, $updates);
        });
    }

    public function addProduct(
        Catalog $catalog,
        string $cooperativeId,
        string $productId
    ): void {
        $this->assertCatalogOwnership($catalog, $cooperativeId);

        if (! $catalog->is_active) {
            throw ValidationException::withMessages([
                'catalog' => ['Impossible de modifier un catalogue inactif.'],
            ]);
        }

        DB::transaction(function () use (
            $catalog,
            $cooperativeId,
            $productId
        ): void {
            $product = Product::query()
                ->whereKey($productId)
                ->where('cooperative_id', $cooperativeId)
                ->where('is_active', true)
                ->first();

            if ($product === null) {
                throw ValidationException::withMessages([
                    'product_id' => [
                        'Le produit est introuvable, inactif ou appartient à une autre coopérative.',
                    ],
                ]);
            }

            $catalog->products()->syncWithoutDetaching([$product->getKey()]);
        });
    }

    public function removeProduct(
        Catalog $catalog,
        string $cooperativeId,
        string $productId
    ): void {
        $this->assertCatalogOwnership($catalog, $cooperativeId);

        DB::transaction(function () use ($catalog, $productId): void {
            $catalog->products()->detach($productId);
        });
    }

    public function delete(
        Catalog $catalog,
        string $cooperativeId
    ): void {
        $this->assertCatalogOwnership($catalog, $cooperativeId);

        DB::transaction(function () use ($catalog): void {
            // Ne supprime pas les produits : seule l'entité Catalog est supprimée.
            $this->repository->delete($catalog);
        });
    }

    private function assertCatalogOwnership(
        Catalog $catalog,
        string $cooperativeId
    ): void {
        if ($catalog->cooperative_id !== $cooperativeId) {
            throw (new ModelNotFoundException())
                ->setModel(Catalog::class, [$catalog->getKey()]);
        }
    }

    private function generateUniqueSlug(
        string $cooperativeId,
        string $value,
        ?string $ignoreCatalogId = null
    ): string {
        $baseSlug = Str::slug($value);

        if ($baseSlug === '') {
            throw ValidationException::withMessages([
                'slug' => ['Impossible de générer un slug à partir de cette valeur.'],
            ]);
        }

        $slug = $baseSlug;
        $suffix = 2;

        while (
            Catalog::query()
                ->forCooperative($cooperativeId)
                ->where('slug', $slug)
                ->when(
                    $ignoreCatalogId !== null,
                    fn ($query) => $query->where(
                        'id',
                        '!=',
                        $ignoreCatalogId
                    )
                )
                ->exists()
        ) {
            $slug = $baseSlug . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }
}
