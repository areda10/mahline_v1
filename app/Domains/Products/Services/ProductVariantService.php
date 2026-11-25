<?php

declare(strict_types=1);

namespace App\Domains\Products\Services;

use App\Domains\Audit\Contracts\AuditWriter;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Products\Models\Product;
use App\Domains\Products\Models\ProductUnit;
use App\Domains\Products\Models\ProductVariant;
use App\Shared\Contracts\StockUnitChangeGuard;
use App\Shared\DTOs\AuditEntryDTO;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ProductVariantService
{
    public function __construct(
        private readonly StockUnitChangeGuard $stockUnitGuard,
        private readonly AuditWriter $audit,
    ) {}

    public function create(
        Product $product,
        array $attributes,
        ?User $actor = null,
    ): ProductVariant {
        return DB::transaction(function () use (
            $product,
            $attributes,
            $actor,
        ): ProductVariant {
            $product = Product::withTrashed()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();
            $this->assertProductIsAvailable($product);

            if (($product->product_type ?? 'simple') !== 'variant') {
                throw ValidationException::withMessages([
                    'product_type' => ['Les variantes ne peuvent être créées que pour un produit déclaré « avec variantes » dès sa création.'],
                ]);
            }

            // La conversion d'un produit simple avec unité/stock parent doit passer
            // par le transfert atomique du domaine Inventory avant toute variante.
            if ($product->unit_id !== null) {
                throw ValidationException::withMessages([
                    'unit_id' => ['La conversion vers des variantes doit d’abord répartir le stock parent via le processus Inventory.'],
                ]);
            }

            $name = trim((string) ($attributes['name'] ?? ''));
            $normalizedLabel = $this->normalizeLabel($name);

            if ($normalizedLabel === '') {
                throw ValidationException::withMessages([
                    'name' => [
                        'Le libellé de la variante est obligatoire.',
                    ],
                ]);
            }

            $isActive = (bool) ($attributes['is_active'] ?? true);
            if ($isActive) {
                $this->assertUniqueActiveLabel($product, $normalizedLabel);
            }

            $variant = new ProductVariant;

            // La variante appartient au produit.
            // La coopérative est déterminée via Product::cooperative_id.
            $variant->product_id = $product->getKey();
            $variant->label = $name;
            $variant->normalized_label = $normalizedLabel;
            $variant->sku = $attributes['sku'] ?? null;
            $variant->price = $this->validatedPrice($attributes['price'] ?? null);
            $variant->unit_id = $this->validatedUnitId($attributes['unit_id'] ?? null);
            $variant->attributes = $attributes['attributes'] ?? null;
            $variant->is_active = $isActive;

            $variant->save();
            $this->record($actor, $product, $variant, 'product_variant.created', 'created', null, $this->snapshot($variant));

            return $variant;
        });
    }

    public function update(
        ProductVariant $variant,
        array $attributes,
        ?User $actor = null,
    ): ProductVariant {
        return DB::transaction(function () use (
            $variant,
            $attributes,
            $actor,
        ): ProductVariant {
            $variantReference = ProductVariant::query()
                ->whereKey($variant->getKey())
                ->firstOrFail();
            $product = Product::withTrashed()
                ->whereKey($variantReference->product_id)
                ->lockForUpdate()
                ->first();
            $variant = ProductVariant::query()
                ->whereKey($variantReference->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($product !== null && ($product->product_type ?? 'simple') !== 'variant') {
                throw ValidationException::withMessages([
                    'product_type' => ['Ce produit a été créé comme produit simple et ne peut pas recevoir de variantes.'],
                ]);
            }

            if ($product !== null && $product->unit_id !== null) {
                throw ValidationException::withMessages([
                    'unit_id' => ['Un produit avec variantes ne peut pas porter d’unité parent.'],
                ]);
            }

            if ($product === null || $product->trashed()) {
                throw ValidationException::withMessages([
                    'product' => [
                        'Le produit associé n’est pas disponible.',
                    ],
                ]);
            }

            $before = $this->snapshot($variant);
            $newActiveState = (bool) ($attributes['is_active'] ?? $variant->is_active);

            if (array_key_exists('name', $attributes)) {
                $name = trim((string) $attributes['name']);
                $normalizedLabel = $this->normalizeLabel($name);

                if ($normalizedLabel === '') {
                    throw ValidationException::withMessages([
                        'name' => [
                            'Le libellé de la variante est obligatoire.',
                        ],
                    ]);
                }

                if ($newActiveState) {
                    $this->assertUniqueActiveLabel(
                        $product,
                        $normalizedLabel,
                        $variant,
                    );
                }

                $variant->label = $name;
                $variant->normalized_label = $normalizedLabel;
            }

            if (array_key_exists('sku', $attributes)) {
                $variant->sku = $attributes['sku'];
            }

            $unitId = $this->validatedUnitId(
                $attributes['unit_id'] ?? $variant->unit_id,
            );

            if ($unitId !== $variant->unit_id
                && $this->stockUnitGuard->hasStockRecord(
                    'variant',
                    (string) $variant->getKey(),
                )) {
                throw ValidationException::withMessages([
                    'unit_id' => ['L’unité ne peut plus être modifiée après l’initialisation du stock de cette variante.'],
                ]);
            }

            $variant->unit_id = $unitId;

            if (array_key_exists('price', $attributes)) {
                $variant->price = $this->validatedPrice($attributes['price']);
            }

            if (array_key_exists('attributes', $attributes)) {
                $variant->attributes = $attributes['attributes'];
            }

            if (array_key_exists('is_active', $attributes)) {
                if ($newActiveState && ! $variant->is_active && ! array_key_exists('name', $attributes)) {
                    $this->assertUniqueActiveLabel($product, $variant->normalized_label, $variant);
                }

                $variant->is_active = $newActiveState;
            }

            $variant->save();
            $variant->refresh();

            $after = $this->snapshot($variant);
            if ($before !== $after) {
                $this->record($actor, $product, $variant, 'product_variant.updated', 'updated', $before, $after);
            }

            return $variant;
        });
    }


    public function deactivate(ProductVariant $variant, ?User $actor = null): ProductVariant
    {
        return $this->update($variant, ['is_active' => false], $actor);
    }

    public function activate(ProductVariant $variant, ?User $actor = null): ProductVariant
    {
        return $this->update($variant, ['is_active' => true], $actor);
    }

    public function delete(ProductVariant $variant, ?User $actor = null): bool
    {
        return DB::transaction(function () use ($variant, $actor): bool {
            $locked = ProductVariant::query()->whereKey($variant->getKey())->lockForUpdate()->firstOrFail();
            $product = Product::withTrashed()->whereKey($locked->product_id)->lockForUpdate()->firstOrFail();
            if ($product->trashed()) {
                throw ValidationException::withMessages([
                    'product' => ['Impossible de gérer une variante dont le produit parent est supprimé.'],
                ]);
            }

            $before = $this->snapshot($locked);
            $deleted = (bool) $locked->delete();

            if ($deleted) {
                $this->record($actor, $product, $locked, 'product_variant.deleted', 'deleted', $before, $this->snapshot($locked) + ['deleted' => true]);
            }

            return $deleted;
        });
    }

    public function restore(ProductVariant $variant, ?User $actor = null): bool
    {
        return DB::transaction(function () use ($variant, $actor): bool {
            $locked = ProductVariant::withTrashed()->whereKey($variant->getKey())->lockForUpdate()->firstOrFail();
            $product = Product::withTrashed()->whereKey($locked->product_id)->lockForUpdate()->firstOrFail();
            if ($product->trashed()) {
                throw ValidationException::withMessages([
                    'product' => ['Impossible de restaurer une variante dont le produit parent est supprimé.'],
                ]);
            }

            if (! $locked->trashed()) {
                return false;
            }

            if ($locked->is_active) {
                $this->assertUniqueActiveLabel($product, $locked->normalized_label, $locked);
            }

            $before = $this->snapshot($locked);
            $locked->restore();
            $locked->refresh();

            $this->record($actor, $product, $locked, 'product_variant.restored', 'restored',
                ['deleted' => true] + $before,
                ['deleted' => false] + $this->snapshot($locked));

            return true;
        });
    }

    /** @return array<string, mixed> */
    private function snapshot(ProductVariant $variant): array
    {
        return [
            'product_id' => (string) $variant->product_id,
            'label' => $variant->label,
            'normalized_label' => $variant->normalized_label,
            'sku' => $variant->sku,
            'price' => $variant->price,
            'unit_id' => $variant->unit_id,
            'attributes' => $variant->attributes,
            'is_active' => (bool) $variant->is_active,
            'deleted' => $variant->trashed(),
        ];
    }

    private function record(
        ?User $actor,
        Product $product,
        ProductVariant $variant,
        string $eventKey,
        string $action,
        ?array $oldValues,
        ?array $newValues,
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
            subjectType: ProductVariant::class,
            subjectId: (string) $variant->getKey(),
            oldValues: $oldValues,
            newValues: $newValues,
        ));
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

    private function assertProductIsAvailable(
        Product $product,
    ): void {
        if (! $product->is_active || $product->trashed()) {
            throw ValidationException::withMessages([
                'product' => [
                    'Impossible de gérer une variante d’un produit inactif ou supprimé.',
                ],
            ]);
        }
    }

    private function assertUniqueActiveLabel(
        Product $product,
        string $normalizedLabel,
        ?ProductVariant $except = null,
    ): void {
        $query = ProductVariant::query()
            ->where('product_id', $product->getKey())
            ->where('is_active', true)
            ->where('normalized_label', $normalizedLabel);

        if ($except !== null) {
            $query->whereKeyNot($except->getKey());
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'name' => [
                    'Une variante active de ce produit utilise déjà ce libellé.',
                ],
            ]);
        }
    }

    private function validatedPrice(mixed $price): string
    {
        if ($price === null || $price === '') {
            throw ValidationException::withMessages([
                'price' => ['Le prix de la variante est obligatoire.'],
            ]);
        }

        $value = (string) $price;
        if (! preg_match('/^\d{1,12}(?:\.\d{1,2})?$/', $value)) {
            throw ValidationException::withMessages([
                'price' => ['Le prix de la variante doit être positif et comporter au maximum deux décimales.'],
            ]);
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '0');
        $fraction = str_pad($fraction, 2, '0');
        if ((int) $whole === 0 && (int) $fraction === 0) {
            throw ValidationException::withMessages([
                'price' => ['Le prix de la variante doit être strictement positif.'],
            ]);
        }

        return ((string) (int) $whole).'.'.$fraction;
    }

    private function normalizeLabel(string $label): string
    {
        $label = Str::ascii(trim($label));
        $label = Str::lower($label);

        return preg_replace('/\s+/', ' ', $label) ?? '';
    }
}
