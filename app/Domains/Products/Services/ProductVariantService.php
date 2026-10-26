<?php

declare(strict_types=1);

namespace App\Domains\Products\Services;

use App\Domains\Products\Models\Product;
use App\Domains\Products\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ProductVariantService
{
    public function create(
        Product $product,
        array $attributes,
    ): ProductVariant {
        return DB::transaction(function () use (
            $product,
            $attributes,
        ): ProductVariant {
            $this->assertProductIsAvailable($product);

            $name = trim((string) ($attributes['name'] ?? ''));
            $normalizedLabel = $this->normalizeLabel($name);

            if ($normalizedLabel === '') {
                throw ValidationException::withMessages([
                    'name' => [
                        'Le libellé de la variante est obligatoire.',
                    ],
                ]);
            }

            $this->assertUniqueActiveLabel(
                $product,
                $normalizedLabel,
            );

            $variant = new ProductVariant();

            // La variante appartient au produit.
            // La coopérative est déterminée via Product::cooperative_id.
            $variant->product_id = $product->getKey();
            $variant->label = $name;
            $variant->normalized_label = $normalizedLabel;
            $variant->sku = $attributes['sku'] ?? null;
            $variant->attributes = $attributes['attributes'] ?? null;
            $variant->is_active = $attributes['is_active'] ?? true;

            $variant->save();

            return $variant;
        });
    }

    public function update(
        ProductVariant $variant,
        array $attributes,
    ): ProductVariant {
        return DB::transaction(function () use (
            $variant,
            $attributes,
        ): ProductVariant {
            $variant->loadMissing('product');

            $product = $variant->product;

            if ($product === null || ! $product->is_active) {
                throw ValidationException::withMessages([
                    'product' => [
                        'Le produit associé n’est pas disponible.',
                    ],
                ]);
            }

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

                if ($variant->is_active) {
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

            if (array_key_exists('attributes', $attributes)) {
                $variant->attributes = $attributes['attributes'];
            }

            if (array_key_exists('is_active', $attributes)) {
                $newActiveState = (bool) $attributes['is_active'];

                if ($newActiveState && ! $variant->is_active) {
                    $this->assertUniqueActiveLabel(
                        $product,
                        $variant->normalized_label,
                        $variant,
                    );
                }

                $variant->is_active = $newActiveState;
            }

            $variant->save();

            return $variant->refresh();
        });
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

    private function normalizeLabel(string $label): string
    {
        $label = Str::ascii(trim($label));
        $label = Str::lower($label);

        return preg_replace('/\s+/', ' ', $label) ?? '';
    }
}
