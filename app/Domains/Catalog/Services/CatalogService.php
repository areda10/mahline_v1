<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Services;

use App\Domains\Audit\Contracts\AuditWriter;
use App\Domains\Catalog\Models\Catalog;
use App\Domains\Catalog\Repositories\CatalogRepository;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Products\Models\Product;
use App\Shared\DTOs\AuditEntryDTO;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CatalogService
{
    public function __construct(
        private readonly CatalogRepository $repository,
        private readonly AuditWriter $audit,
    ) {}

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
        array $attributes,
        ?User $actor = null,
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
            $name,
            $actor,
        ): Catalog {
            $slug = $this->generateUniqueSlug(
                $cooperativeId,
                (string) ($attributes['slug'] ?? $name)
            );

            $catalog = $this->repository->create([
                'cooperative_id' => $cooperativeId,
                'name' => $name,
                'slug' => $slug,
                'description' => $attributes['description'] ?? null,
                'is_active' => $attributes['is_active'] ?? true,
            ]);

            if ($actor !== null) {
                $catalog->forceFill(['created_by' => (string) $actor->getKey()])->save();
            }

            $this->record($actor, $catalog, 'catalog.created', 'created',
                newValues: $this->snapshot($catalog));

            return $catalog;
        });
    }

    public function update(
        Catalog $catalog,
        string $cooperativeId,
        array $attributes,
        ?User $actor = null,
    ): Catalog {
        if ($catalog->cooperative_id !== $cooperativeId) {
            throw (new ModelNotFoundException)
                ->setModel(Catalog::class, [$catalog->getKey()]);
        }

        return DB::transaction(function () use (
            $catalog,
            $cooperativeId,
            $attributes,
            $actor,
        ): Catalog {
            $catalog = Catalog::query()->whereKey($catalog->getKey())->lockForUpdate()->firstOrFail();
            $before = $this->snapshot($catalog);
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

            $updated = $this->repository->update($catalog, $updates);

            if ($actor !== null) {
                $updated->forceFill(['updated_by' => (string) $actor->getKey()])->save();
            }

            if ($before !== $this->snapshot($updated)) {
                $this->record($actor, $updated, 'catalog.updated', 'updated',
                    oldValues: $before, newValues: $this->snapshot($updated));
            }

            return $updated;
        });
    }

    public function addProduct(
        Catalog $catalog,
        string $cooperativeId,
        string $productId,
        ?User $actor = null,
    ): void {
        $this->assertCatalogOwnership($catalog, $cooperativeId);

        DB::transaction(function () use (
            $catalog,
            $cooperativeId,
            $productId,
            $actor,
        ): void {
            $catalog = Catalog::query()->whereKey($catalog->getKey())->lockForUpdate()->firstOrFail();
            if (! $catalog->is_active) {
                throw ValidationException::withMessages([
                    'catalog' => ['Impossible de modifier un catalogue inactif.'],
                ]);
            }

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

            $alreadyAttached = $catalog->products()->whereKey($product->getKey())->exists();
            $catalog->products()->syncWithoutDetaching([$product->getKey()]);

            if (! $alreadyAttached) {
                $this->record($actor, $catalog, 'catalog.product_added', 'product_added',
                    metadata: ['product_id' => (string) $product->getKey()]);
            }
        });
    }

    public function removeProduct(
        Catalog $catalog,
        string $cooperativeId,
        string $productId,
        ?User $actor = null,
    ): void {
        $this->assertCatalogOwnership($catalog, $cooperativeId);

        DB::transaction(function () use (
            $catalog,
            $cooperativeId,
            $productId,
            $actor,
        ): void {
            $catalog = Catalog::query()->whereKey($catalog->getKey())->lockForUpdate()->firstOrFail();
            $productExists = Product::query()
                ->whereKey($productId)
                ->where('cooperative_id', $cooperativeId)
                ->exists();

            if (! $productExists) {
                throw ValidationException::withMessages([
                    'product' => [
                        'Le produit est introuvable ou appartient à une autre coopérative.',
                    ],
                ]);
            }

            $wasAttached = $catalog->products()->whereKey($productId)->exists();
            $catalog->products()->detach($productId);

            if ($wasAttached) {
                $this->record($actor, $catalog, 'catalog.product_removed', 'product_removed',
                    metadata: ['product_id' => $productId]);
            }
        });
    }

    public function delete(
        Catalog $catalog,
        string $cooperativeId,
        ?User $actor = null,
    ): void {
        $this->assertCatalogOwnership($catalog, $cooperativeId);

        DB::transaction(function () use ($catalog, $actor): void {
            $locked = Catalog::query()->whereKey($catalog->getKey())->lockForUpdate()->firstOrFail();
            $before = $this->snapshot($locked);
            if ($actor !== null) {
                $locked->deleted_by = (string) $actor->getKey();
                $locked->save();
            }
            // Ne supprime pas les produits : seule l'entité Catalog est supprimée.
            $this->repository->delete($locked);
            $this->record($actor, $locked, 'catalog.deleted', 'deleted',
                oldValues: $before, newValues: ['deleted' => true]);
        });
    }

    /** @return array<string, mixed> */
    private function snapshot(Catalog $catalog): array
    {
        return [
            'cooperative_id' => (string) $catalog->cooperative_id,
            'name' => $catalog->name,
            'slug' => $catalog->slug,
            'description' => $catalog->description,
            'is_active' => (bool) $catalog->is_active,
        ];
    }

    /** @param array<string, mixed>|null $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @param  array<string, mixed>|null  $metadata
     */
    private function record(
        ?User $actor,
        Catalog $catalog,
        string $eventKey,
        string $action,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null,
    ): void {
        if ($actor === null) {
            return;
        }

        $this->audit->record(new AuditEntryDTO(
            eventKey: $eventKey,
            action: $action,
            actorType: User::class,
            actorId: (string) $actor->getKey(),
            cooperativeId: (string) $catalog->cooperative_id,
            subjectType: Catalog::class,
            subjectId: (string) $catalog->getKey(),
            oldValues: $oldValues,
            newValues: $newValues,
            metadata: $metadata,
        ));
    }

    private function assertCatalogOwnership(
        Catalog $catalog,
        string $cooperativeId
    ): void {
        if ($catalog->cooperative_id !== $cooperativeId) {
            throw (new ModelNotFoundException)
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
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
