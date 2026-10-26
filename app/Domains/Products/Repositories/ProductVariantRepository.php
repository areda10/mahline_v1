<?php

declare(strict_types=1);

namespace App\Domains\Products\Repositories;

use App\Domains\Products\Models\ProductVariant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ProductVariantRepository
{
    public function paginateForProduct(
        string $productId,
        int $perPage = 20,
    ): LengthAwarePaginator {
        return ProductVariant::query()
            ->where('product_id', $productId)
            ->orderBy('label')
            ->paginate($perPage);
    }

    public function findForProductOrFail(
        string $variantId,
        string $productId,
    ): ProductVariant {
        return ProductVariant::query()
            ->where('product_id', $productId)
            ->findOrFail($variantId);
    }

    public function delete(ProductVariant $variant): bool
    {
        return (bool) $variant->delete();
    }
}
