<?php

declare(strict_types=1);

namespace App\Domains\Products\Http\Controllers;

use App\Domains\Categories\Models\Category;
use App\Domains\Cooperatives\Enums\CooperativeStatus;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Products\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ProductStorefrontController
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'category_id' => ['nullable', 'string', 'size:26'],
            'cooperative_id' => ['nullable', 'string', 'size:26'],
            'min_price' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'sort' => ['nullable', 'in:newest,price_asc,price_desc,name'],
        ]);

        $products = Product::query()
            ->with([
                'cooperative.logo',
                'primaryImage',
                'unit',
                'activeVariants.unit',
                'categories' => static fn ($query) => $query->where('categories.is_active', true),
            ])
            ->where('is_active', true);

        $this->applyPublishedVisibility($products);

        if (! empty($filters['q'])) {
            $term = trim($filters['q']);
            $products->where(function (Builder $query) use ($term): void {
                $query->where('name', 'like', '%'.$term.'%')
                    ->orWhere('description', 'like', '%'.$term.'%')
                    ->orWhereHas('cooperative', static fn (Builder $cooperative) => $cooperative
                        ->where('name', 'like', '%'.$term.'%'));
            });
        }

        if (! empty($filters['category_id'])) {
            $products->whereHas('categories', static fn (Builder $query) => $query
                ->whereKey($filters['category_id'])
                ->where('categories.is_active', true));
        }

        if (! empty($filters['cooperative_id'])) {
            $products->where('cooperative_id', $filters['cooperative_id']);
        }

        if (isset($filters['min_price'])) {
            $minimum = $filters['min_price'];
            $products->where(function (Builder $query) use ($minimum): void {
                $query->where('price', '>=', $minimum)
                    ->orWhereHas('activeVariants', static fn (Builder $variants) => $variants
                        ->where('price', '>=', $minimum));
            });
        }

        if (isset($filters['max_price'])) {
            $maximum = $filters['max_price'];
            $products->where(function (Builder $query) use ($maximum): void {
                $query->where('price', '<=', $maximum)
                    ->orWhereHas('activeVariants', static fn (Builder $variants) => $variants
                        ->where('price', '<=', $maximum)
                        ->orWhereNull('price'));
            });
        }

        if (in_array($filters['sort'] ?? '', ['price_asc', 'price_desc'], true)) {
            $products->withMin('activeVariants', 'price');
        }

        match ($filters['sort'] ?? 'newest') {
            'price_asc' => $products->orderByRaw('COALESCE(active_variants_min_price, products.price) ASC')->orderBy('name'),
            'price_desc' => $products->orderByRaw('COALESCE(active_variants_min_price, products.price) DESC')->orderBy('name'),
            'name' => $products->orderBy('name'),
            default => $products->latest(),
        };

        $categories = Category::query()
            ->where('is_active', true)
            ->whereHas('products', function (Builder $query): void {
                $query->where('products.is_active', true);
                $this->applyPublishedVisibility($query);
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $cooperatives = Cooperative::query()
            ->where('status', CooperativeStatus::ACTIVE)
            ->whereHas('products', function (Builder $query): void {
                $query->where('products.is_active', true);
                $this->applyPublishedVisibility($query);
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('storefront.products.index', [
            'products' => $products->paginate(12)->withQueryString(),
            'categories' => $categories,
            'cooperatives' => $cooperatives,
            'filters' => $filters,
        ]);
    }

    public function show(string $product): View
    {
        $query = Product::query()
            ->with([
                'cooperative.logo',
                'primaryImage',
                'images',
                'unit',
                'activeVariants' => static fn (Builder $variants) => $variants
                    ->whereNotNull('unit_id')
                    ->whereHas('unit')
                    ->where(function (Builder $price) {
                        $price->whereNotNull('price')
                            ->orWhereHas('product', static fn (Builder $parent) => $parent->whereNotNull('price'));
                    })
                    ->with('unit'),
                'categories' => static fn ($categories) => $categories
                    ->where('categories.is_active', true),
            ])
            ->whereKey($product)
            ->where('is_active', true);

        $this->applyPublishedVisibility($query);

        $productModel = $query->firstOrFail();

        $relatedProducts = Product::query()
            ->with(['primaryImage', 'unit', 'activeVariants'])
            ->where('id', '!=', $productModel->getKey())
            ->where('cooperative_id', $productModel->cooperative_id)
            ->where('is_active', true);

        $this->applyPublishedVisibility($relatedProducts);

        $relatedProducts = $relatedProducts->latest()
            ->limit(4)
            ->get();

        $hasVariants = $productModel->variants()->withTrashed()->exists();

        return view('storefront.products.show', [
            'product' => $productModel,
            'relatedProducts' => $relatedProducts,
            'hasVariants' => $hasVariants,
            'variantsAvailable' => $productModel->activeVariants->isNotEmpty(),
        ]);
    }

    private function applyPublishedVisibility(Builder $query): void
    {
        $query
            ->whereHas('cooperative', static fn (Builder $cooperative) => $cooperative
                ->where('status', CooperativeStatus::ACTIVE))
            ->whereHas('catalogs', static fn (Builder $catalogs) => $catalogs
                ->where('is_active', true)
                ->whereColumn('catalogs.cooperative_id', 'products.cooperative_id'))
            ->where(function (Builder $product): void {
                $product->where(function (Builder $simple): void {
                    $simple->whereNotNull('unit_id')
                        ->whereNotNull('price')
                        ->whereDoesntHave('variants', static fn (Builder $variants) => $variants->withTrashed());
                })->orWhereHas('activeVariants', static function (Builder $variants): void {
                    $variants->whereNotNull('unit_id')
                        ->whereHas('unit')
                        ->where(function (Builder $price): void {
                            $price->whereNotNull('price')
                                ->orWhereHas('product', static fn (Builder $parent) => $parent->whereNotNull('price'));
                        });
                });
            });
    }
}
