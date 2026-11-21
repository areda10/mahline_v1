<?php

declare(strict_types=1);

namespace App\Domains\Products\Services;

use App\Domains\Audit\Contracts\AuditWriter;
use App\Domains\Catalog\Models\Catalog;
use App\Domains\Categories\Models\Category;
use App\Domains\Identity\Users\Models\User;
use App\Shared\Contracts\StockUnitChangeGuard;
use App\Domains\Products\Contracts\CheckoutCatalog;
use App\Domains\Products\DTOs\CheckoutProductData;
use App\Domains\Products\DTOs\InventoryStockableData;
use App\Domains\Products\Models\Product;
use App\Domains\Products\Models\ProductUnit;
use App\Domains\Products\Models\ProductVariant;
use App\Domains\Products\Repositories\ProductRepository;
use App\Shared\DTOs\AuditEntryDTO;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ProductService implements CheckoutCatalog
{
    public function __construct(
        private readonly ProductRepository $repository,
        private readonly AuditWriter $audit,
        private readonly StockUnitChangeGuard $stockUnitGuard,
    ) {}

    public function resolveForCheckout(string $productId, ?string $variantId): ?CheckoutProductData
    {
        $product = Product::query()
            ->whereKey($productId)
            ->lockForUpdate()
            ->first();

        if ($product === null || ! $product->is_active) {
            return null;
        }

        $variantRequested = $variantId !== null && $variantId !== '';
        $variant = null;

        if ($variantRequested) {
            $variant = ProductVariant::query()
                ->whereKey($variantId)
                ->where('product_id', $product->getKey())
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();
        }

        $hasVariants = $product->variants()->exists();
        $unit = $variant !== null ? $variant->unit : ($variantRequested ? null : $product->unit);

        return new CheckoutProductData(
            productId: (string) $product->getKey(),
            cooperativeId: (string) $product->cooperative_id,
            productName: (string) $product->name,
            productSlug: (string) $product->slug,
            currency: (string) $product->currency,
            effectivePrice: $variant?->price ?? $product->price,
            hasVariants: $hasVariants,
            hasParentUnit: $product->unit_id !== null,
            variantRequested: $variantRequested,
            variantFound: ! $variantRequested || $variant !== null,
            variantId: $variant === null ? null : (string) $variant->getKey(),
            variantLabel: $variant?->label,
            variantSku: $variant?->sku,
            unitCode: $unit?->code,
            unitLabel: $unit?->label,
        );
    }

    public function create(
        string $cooperativeId,
        array $attributes,
        ?User $actor = null,
    ): Product {
        return DB::transaction(function () use (
            $cooperativeId,
            $attributes,
            $actor,
        ): Product {
            $categoryIds = $this->normalizeIds(
                $attributes['category_ids'] ?? [],
            );

            $catalogIds = $this->normalizeIds(
                $attributes['catalog_ids'] ?? [],
            );

            $this->assertCategoriesAvailable(
                $categoryIds,
                $cooperativeId,
            );

            $this->assertCatalogsOwnedByCooperative(
                $catalogIds,
                $cooperativeId,
            );

            $unitId = $this->validatedUnitId($attributes['unit_id'] ?? null);

            $name = trim((string) ($attributes['name'] ?? ''));

            if ($name === '') {
                throw ValidationException::withMessages([
                    'name' => [
                        'Le nom du produit est obligatoire.',
                    ],
                ]);
            }

            $product = $this->repository->create([
                'cooperative_id' => $cooperativeId,
                'name' => $name,
                'slug' => $this->uniqueSlug(
                    $cooperativeId,
                    $name,
                ),
                'description' => $attributes['description'] ?? null,
                'price' => $attributes['price'] ?? null,
                'currency' => 'MAD',
                'unit_id' => $unitId,
                'is_active' => (bool) ($attributes['is_active'] ?? true),
            ]);

            if ($actor !== null) {
                $product->forceFill(['created_by' => (string) $actor->getKey()])->save();
            }

            $product->categories()->sync($categoryIds);
            $product->catalogs()->sync($catalogIds);

            if (
                ! $product->categories()
                    ->where('categories.is_active', true)
                    ->exists()
            ) {
                $product->is_active = false;
                $product->save();
            }

            $this->record($actor, $product, 'product.created', 'created',
                newValues: $this->snapshot($product));

            return $product->load([
                'categories',
                'catalogs',
                'variants',
            ]);
        });
    }

    public function update(
        Product $product,
        string $cooperativeId,
        array $attributes,
        ?User $actor = null,
    ): Product {
        return DB::transaction(function () use (
            $product,
            $cooperativeId,
            $attributes,
            $actor,
        ): Product {
            $product = Product::query()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();
            if ((string) $product->cooperative_id !== $cooperativeId) {
                throw ValidationException::withMessages([
                    'product' => [
                        'Ce produit ne relève pas de cette coopérative.',
                    ],
                ]);
            }

            $before = $this->snapshot($product);

            $hasVariants = $product->variants()->exists();

            if ($hasVariants) {
                if ($product->unit_id !== null) {
                    throw ValidationException::withMessages([
                        'unit_id' => ['Un produit avec variantes ne peut pas porter d’unité parent.'],
                    ]);
                }

                if (array_key_exists('unit_id', $attributes)) {
                    throw ValidationException::withMessages([
                        'unit_id' => ['L’unité se définit sur chaque variante pour ce produit.'],
                    ]);
                }

                $unitId = null;
            } else {
                $unitId = $this->validatedUnitId(
                    $attributes['unit_id'] ?? $product->unit_id,
                );
            }

            if (! $hasVariants && $unitId !== $product->unit_id
                && $this->stockUnitGuard->hasStockRecord(
                    'product',
                    (string) $product->getKey(),
                )) {
                throw ValidationException::withMessages([
                    'unit_id' => ['L’unité ne peut plus être modifiée après l’initialisation du stock. Effectuez un ajustement compensatoire dans Inventory si nécessaire.'],
                ]);
            }

            $categoryIds = array_key_exists('category_ids', $attributes)
                ? $this->normalizeIds($attributes['category_ids'] ?? [])
                : null;

            $catalogIds = array_key_exists('catalog_ids', $attributes)
                ? $this->normalizeIds($attributes['catalog_ids'] ?? [])
                : null;

            if ($categoryIds !== null) {
                $this->assertCategoriesAvailable(
                    $categoryIds,
                    $cooperativeId,
                );
            }

            if ($catalogIds !== null) {
                $this->assertCatalogsOwnedByCooperative(
                    $catalogIds,
                    $cooperativeId,
                );
            }

            $changes = [];

            if (! $hasVariants) {
                $changes['unit_id'] = $unitId;
            }

            if (array_key_exists('name', $attributes)) {
                $name = trim((string) $attributes['name']);

                if ($name === '') {
                    throw ValidationException::withMessages([
                        'name' => [
                            'Le nom du produit est obligatoire.',
                        ],
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

            if (array_key_exists('price', $attributes)) {
                $changes['price'] = $attributes['price'];
            }

            if (array_key_exists('is_active', $attributes)) {
                $requestedActive = (bool) $attributes['is_active'];

                if ($requestedActive && ! $product->is_active) {
                    throw ValidationException::withMessages([
                        'is_active' => [
                            'Utilisez l’action de réactivation pour réactiver ce produit.',
                        ],
                    ]);
                }

                $changes['is_active'] = $requestedActive;
            }

            // Le propriétaire ne peut pas être changé via update.
            $this->repository->update($product, $changes);

            if ($categoryIds !== null) {
                $product->categories()->sync($categoryIds);
            }

            if ($catalogIds !== null) {
                $product->catalogs()->sync($catalogIds);
            }

            if (
                ! $product->categories()
                    ->where('categories.is_active', true)
                    ->exists()
                && $product->is_active
            ) {
                $product->is_active = false;
                $product->save();
            }

            $product->refresh();

            if ($actor !== null) {
                $product->forceFill(['updated_by' => (string) $actor->getKey()])->save();
            }

            if ($before !== $this->snapshot($product)) {
                $this->record($actor, $product, 'product.updated', 'updated',
                    oldValues: $before, newValues: $this->snapshot($product));
            }

            return $product->refresh()
                ->load([
                    'categories',
                    'catalogs',
                    'variants',
                ]);
        });
    }

    /**
     * Désactive un produit sans le supprimer.
     */
    public function deactivate(Product $product, ?User $actor = null): Product
    {
        return DB::transaction(function () use ($product, $actor): Product {
            $locked = Product::query()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();
            if (! $locked->is_active) {
                return $locked;
            }

            $before = $this->snapshot($locked);
            $locked->is_active = false;
            if ($actor !== null) {
                $locked->updated_by = (string) $actor->getKey();
            }
            $locked->save();

            $this->record($actor, $locked, 'product.deactivated', 'deactivated',
                oldValues: $before, newValues: $this->snapshot($locked));

            return $locked->refresh();
        });
    }

    /**
     * Supprime logiquement un produit.
     *
     * Le modèle Product utilise SoftDeletes.
     */
    public function delete(Product $product, ?User $actor = null): bool
    {
        return DB::transaction(function () use ($product, $actor): bool {
            $locked = Product::query()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();
            $before = $this->snapshot($locked);
            if ($actor !== null) {
                $locked->deleted_by = (string) $actor->getKey();
                $locked->save();
            }

            $deleted = $this->repository->delete($locked);
            if ($deleted) {
                $this->record($actor, $locked, 'product.deleted', 'deleted',
                    oldValues: $before, newValues: ['deleted' => true]);
            }

            return $deleted;
        });
    }

    /**
     * Restaure logiquement un produit sans modifier ses variantes.
     */
    public function restore(Product $product, ?User $actor = null): bool
    {
        return DB::transaction(function () use ($product, $actor): bool {
            $trashedProduct = Product::withTrashed()
                ->whereKey($product->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $trashedProduct->trashed()) {
                return false;
            }

            $before = $this->snapshot($trashedProduct);

            // La restauration du parent ne touche jamais aux variantes.
            $trashedProduct->restore();

            // Un produit sans catégorie active ne peut pas redevenir publiable.
            $hasActiveCategory = $trashedProduct->categories()
                ->where('categories.is_active', true)
                ->where(function ($query) use ($trashedProduct): void {
                    $query->whereNull('categories.cooperative_id')
                        ->orWhere('categories.cooperative_id', $trashedProduct->cooperative_id);
                })
                ->exists();

            if ($trashedProduct->is_active && ! $hasActiveCategory) {
                $trashedProduct->is_active = false;
            }

            if ($actor !== null) {
                $trashedProduct->updated_by = (string) $actor->getKey();
            }
            $trashedProduct->save();

            $this->record($actor, $trashedProduct, 'product.restored', 'restored',
                oldValues: ['deleted' => true] + $before,
                newValues: ['deleted' => false] + $this->snapshot($trashedProduct));

            return true;
        });
    }

    private function validatedUnitId(mixed $unitId): string
    {
        if (! is_string($unitId) || $unitId === '' || ! ProductUnit::query()
            ->whereKey($unitId)
            ->where('is_active', true)
            ->whereIn('code', ProductUnit::V1_CODES)
            ->exists()) {
            throw ValidationException::withMessages([
                'unit_id' => ['Sélectionnez une unité de stock active parmi les unités autorisées.'],
            ]);
        }

        return $unitId;
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

    public function reactivate(Product $product, ?User $actor = null): Product
    {
        return DB::transaction(function () use ($product, $actor): Product {
            $lockedProduct = Product::query()
                ->whereKey($product->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            // Un produit déjà actif ne nécessite aucune modification.
            if ($lockedProduct->is_active) {
                return $lockedProduct;
            }

            $hasActiveCategory = $lockedProduct->categories()
                ->where('categories.is_active', true)
                ->where(function ($query) use ($lockedProduct): void {
                    $query->whereNull('categories.cooperative_id')
                        ->orWhere(
                            'categories.cooperative_id',
                            $lockedProduct->cooperative_id,
                        );
                })
                ->exists();

            if (! $hasActiveCategory) {
                throw ValidationException::withMessages([
                    'product' => [
                        'Un produit doit être associé à au moins une catégorie active pour être réactivé.',
                    ],
                ]);
            }

            $before = $this->snapshot($lockedProduct);
            $lockedProduct->is_active = true;
            if ($actor !== null) {
                $lockedProduct->updated_by = (string) $actor->getKey();
            }
            $lockedProduct->save();

            $this->record($actor, $lockedProduct, 'product.reactivated', 'reactivated',
                oldValues: $before, newValues: $this->snapshot($lockedProduct));

            return $lockedProduct->refresh();
        });
    }

    /** @return array<string, mixed> */
    private function snapshot(Product $product): array
    {
        return [
            'cooperative_id' => (string) $product->cooperative_id,
            'name' => $product->name,
            'slug' => $product->slug,
            'description' => $product->description,
            'price' => $product->price,
            'currency' => $product->currency,
            'unit_id' => $product->unit_id,
            'is_active' => (bool) $product->is_active,
            'category_ids' => $product->categories()->orderBy('categories.id')->pluck('categories.id')->map(fn ($id): string => (string) $id)->all(),
            'catalog_ids' => $product->catalogs()->orderBy('catalogs.id')->pluck('catalogs.id')->map(fn ($id): string => (string) $id)->all(),
        ];
    }

    private function record(
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

    public function resolveForInventory(string $stockableType, string $stockableId): ?InventoryStockableData
    {
        if ($stockableType === 'product') {
            $product = Product::withTrashed()->with('unit')->whereKey($stockableId)->lockForUpdate()->first();
            if ($product === null || $product->unit === null) {
                return null;
            }

            $hasAnyVariant = ProductVariant::withTrashed()
                ->where('product_id', $stockableId)
                ->exists();

            if ($hasAnyVariant) {
                return null;
            }

            return new InventoryStockableData(
                cooperativeId: (string) $product->cooperative_id,
                stockableType: 'product',
                stockableId: (string) $product->getKey(),
                unitCode: (string) $product->unit->code,
            );
        }

        if ($stockableType === 'variant') {
            $variantReference = ProductVariant::withTrashed()
                ->whereKey($stockableId)
                ->first();

            if ($variantReference === null) {
                return null;
            }

            $product = Product::withTrashed()
                ->with('unit')
                ->whereKey($variantReference->product_id)
                ->lockForUpdate()
                ->first();

            $variant = ProductVariant::withTrashed()
                ->with('unit')
                ->whereKey($stockableId)
                ->lockForUpdate()
                ->first();

            if ($variant === null || $product === null) {
                return null;
            }

            $unitCode = $variant->unit?->code ?? $product->unit?->code;
            if ($unitCode === null) {
                return null;
            }

            return new InventoryStockableData(
                cooperativeId: (string) $product->cooperative_id,
                stockableType: 'variant',
                stockableId: (string) $variant->getKey(),
                unitCode: (string) $unitCode,
            );
        }

        return null;
    }
}
