<?php

declare(strict_types=1);

namespace App\Domains\Products\Repositories;

use App\Domains\Products\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ProductRepository
{
    public function paginateForCooperative(
        string $cooperativeId,
        int $perPage = 20,
    ): LengthAwarePaginator {
        return Product::query()
            ->forCooperative($cooperativeId)
            ->with(['categories', 'catalogs', 'variants'])
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function findForCooperativeOrFail(
        string $productId,
        string $cooperativeId,
    ): Product {
        return Product::query()
            ->forCooperative($cooperativeId)
            ->with(['categories', 'catalogs', 'variants'])
            ->findOrFail($productId);
    }

    public function create(array $attributes): Product
    {
        return Product::query()->create($attributes);
    }

    public function update(
        Product $product,
        array $attributes,
    ): Product {
        $product->update($attributes);

        return $product->refresh();
    }

    public function delete(Product $product): bool
    {
        return (bool) $product->delete();
    }
}