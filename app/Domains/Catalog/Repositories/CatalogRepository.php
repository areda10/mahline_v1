<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Repositories;

use App\Domains\Catalog\Models\Catalog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

final class CatalogRepository
{
    public function paginateForCooperative(
        string $cooperativeId,
        int $perPage = 20
    ): LengthAwarePaginator {
        return Catalog::query()
            ->forCooperative($cooperativeId)
            ->with('cooperative')
            ->withCount('products')
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function findForCooperativeOrFail(
        string $catalogId,
        string $cooperativeId
    ): Catalog {
        return Catalog::query()
            ->forCooperative($cooperativeId)
            ->with('products')
            ->findOrFail($catalogId);
    }

    public function findActiveForCooperativeOrFail(
        string $catalogId,
        string $cooperativeId
    ): Catalog {
        return Catalog::query()
            ->forCooperative($cooperativeId)
            ->active()
            ->findOrFail($catalogId);
    }

    public function findBySlug(
        string $cooperativeId,
        string $slug
    ): ?Catalog {
        return Catalog::query()
            ->forCooperative($cooperativeId)
            ->where('slug', $slug)
            ->first();
    }

    public function create(array $attributes): Catalog
    {
        return Catalog::query()->create($attributes);
    }

    public function update(
        Catalog $catalog,
        array $attributes
    ): Catalog {
        $catalog->update($attributes);

        return $catalog->refresh();
    }

    public function delete(Catalog $catalog): bool
    {
        return (bool) $catalog->delete();
    }

    public function products(Catalog $catalog): Collection
    {
        return $catalog->products()->get();
    }
}
