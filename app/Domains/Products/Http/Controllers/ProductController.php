<?php

declare(strict_types=1);

namespace App\Domains\Products\Http\Controllers;

use App\Domains\Catalog\Models\Catalog;
use App\Domains\Categories\Models\Category;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Products\Models\Product;
use App\Domains\Products\Models\ProductUnit;
use App\Domains\Products\Models\ProductVariant;
use App\Domains\Products\Repositories\ProductRepository;
use App\Domains\Products\Services\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class ProductController
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly ProductService $productService,
    ) {}

    public function index(string $cooperative): View
    {
        $cooperativeModel = $this->resolveCooperative($cooperative);

        Gate::authorize('viewAny', Product::class);

        $products = $this->productRepository->paginateForCooperative(
            (string) $cooperativeModel->getKey(),
        );

        return view('backoffice.products.index', [
            'cooperative' => $cooperativeModel,
            'products' => $products,
        ]);
    }

    public function create(string $cooperative): View
    {
        $cooperativeModel = $this->resolveCooperative($cooperative);
        $cooperativeId = (string) $cooperativeModel->getKey();

        Gate::authorize('create', [
            Product::class,
            $cooperativeId,
        ]);

        return view('backoffice.products.create', [
            'cooperative' => $cooperativeModel,
            'product' => new Product,
            'productUnits' => ProductUnit::query()->whereIn('code', ProductUnit::V1_CODES)->where('is_active', true)->orderBy('sort_order')->orderBy('label')->get(),
            'hasVariants' => false,
            'categories' => Category::query()
                ->availableFor($cooperativeId)
                ->active()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'catalogs' => Catalog::query()
                ->forCooperative($cooperativeId)
                ->active()
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(
        Request $request,
        string $cooperative,
    ): RedirectResponse {
        $cooperativeModel = $this->resolveCooperative($cooperative);
        $cooperativeId = (string) $cooperativeModel->getKey();

        Gate::authorize('create', [
            Product::class,
            $cooperativeId,
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'unit_id' => ['required', 'string', 'size:26', Rule::exists('product_units', 'id')->where('is_active', true)->whereIn('code', ProductUnit::V1_CODES)],
            'is_active' => ['sometimes', 'boolean'],
            'category_ids' => ['sometimes', 'array'],
            'category_ids.*' => ['required', 'string', 'max:26', 'distinct'],
            'catalog_ids' => ['sometimes', 'array'],
            'catalog_ids.*' => ['required', 'string', 'max:26', 'distinct'],
        ]);

        $data['category_ids'] ??= [];
        $data['catalog_ids'] ??= [];

        $product = $this->productService->create(
            $cooperativeId,
            $data,
            $request->user(),
        );

        return redirect()
            ->route('backoffice.products.show', [
                'cooperative' => $cooperativeId,
                'product' => $product->getKey(),
            ])
            ->with('success', 'Le produit a été créé.');
    }

    public function show(
        string $cooperative,
        string $product,
    ): View {
        $cooperativeModel = $this->resolveCooperative($cooperative);

        $productModel = $this->productRepository
            ->findForCooperativeOrFail(
                $product,
                (string) $cooperativeModel->getKey(),
            );

        Gate::authorize('view', $productModel);
        if (($productModel->product_type ?? 'simple') === 'variant') {
            $productModel->setRelation('variants', ProductVariant::withTrashed()
                ->where('product_id', $productModel->getKey())
                ->orderBy('label')->get());
        }

        return view('backoffice.products.show', [
            'cooperative' => $cooperativeModel,
            'product' => $productModel,
        ]);
    }

    public function edit(
        string $cooperative,
        string $product,
    ): View {
        $cooperativeModel = $this->resolveCooperative($cooperative);
        $cooperativeId = (string) $cooperativeModel->getKey();

        $productModel = $this->productRepository
            ->findForCooperativeOrFail($product, $cooperativeId);

        Gate::authorize('update', $productModel);

        return view('backoffice.products.edit', [
            'cooperative' => $cooperativeModel,
            'product' => $productModel,
            'productUnits' => ProductUnit::query()->whereIn('code', ProductUnit::V1_CODES)->where('is_active', true)->orderBy('sort_order')->orderBy('label')->get(),
            'hasVariants' => ($productModel->product_type ?? 'simple') === 'variant',
            'categories' => Category::query()
                ->availableFor($cooperativeId)
                ->active()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'catalogs' => Catalog::query()
                ->forCooperative($cooperativeId)
                ->active()
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(
        Request $request,
        string $cooperative,
        string $product,
    ): RedirectResponse {
        $cooperativeModel = $this->resolveCooperative($cooperative);
        $cooperativeId = (string) $cooperativeModel->getKey();

        $productModel = $this->productRepository
            ->findForCooperativeOrFail($product, $cooperativeId);

        Gate::authorize('update', $productModel);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:180'],
            'description' => ['sometimes', 'nullable', 'string'],
            'price' => ['sometimes', 'required', 'numeric', 'gt:0', 'decimal:0,2'],
            'unit_id' => ($productModel->product_type ?? 'simple') === 'variant'
                ? ['prohibited']
                : ['required', 'string', 'size:26', Rule::exists('product_units', 'id')->where('is_active', true)->whereIn('code', ProductUnit::V1_CODES)],
            'is_active' => ['sometimes', 'boolean'],
            'category_ids' => ['sometimes', 'array'],
            'category_ids.*' => ['required', 'string', 'max:26', 'distinct'],
            'catalog_ids' => ['sometimes', 'array'],
            'catalog_ids.*' => ['required', 'string', 'max:26', 'distinct'],
        ]);

        $this->productService->update(
            $productModel,
            $cooperativeId,
            $data,
            $request->user(),
        );

        return redirect()
            ->route('backoffice.products.show', [
                'cooperative' => $cooperativeId,
                'product' => $productModel->getKey(),
            ])
            ->with('success', 'Le produit a été mis à jour.');
    }

    public function destroy(
        Request $request,
        string $cooperative,
        string $product,
    ): RedirectResponse {
        $cooperativeModel = $this->resolveCooperative($cooperative);
        $cooperativeId = (string) $cooperativeModel->getKey();

        $productModel = $this->productRepository
            ->findForCooperativeOrFail($product, $cooperativeId);

        Gate::authorize('delete', $productModel);

        // ProductService applique la suppression logique (SoftDeletes).
        $this->productService->delete($productModel, $request->user());

        return redirect()
            ->route('backoffice.products.index', [
                'cooperative' => $cooperativeId,
            ])
            ->with('success', 'Le produit a été supprimé.');
    }

    public function restore(
        Request $request,
        string $cooperative,
        string $product,
    ): RedirectResponse {
        $cooperativeModel = $this->resolveCooperative($cooperative);
        $cooperativeId = (string) $cooperativeModel->getKey();

        $productModel = $this->productRepository
            ->findTrashedForCooperativeOrFail($product, $cooperativeId);

        Gate::authorize('restore', $productModel);

        $this->productService->restore($productModel, $request->user());

        return redirect()
            ->route('backoffice.products.show', [
                'cooperative' => $cooperativeId,
                'product' => $productModel->getKey(),
            ])
            ->with('success', 'Le produit a été restauré.');

    }

    private function resolveCooperative(string $id): Cooperative
    {
        $cooperative = Cooperative::query()->findOrFail($id);

        Gate::authorize('view', $cooperative);

        return $cooperative;
    }

    public function reactivate(
        Request $request,
        string $cooperative,
        string $product,
    ): RedirectResponse {
        $cooperativeModel = $this->resolveCooperative($cooperative);

        $cooperativeId = (string) $cooperativeModel->getKey();

        $productModel = $this->productRepository
            ->findForCooperativeOrFail($product, $cooperativeId);

        Gate::authorize('reactivate', $productModel);

        $this->productService->reactivate($productModel, $request->user());

        return redirect()
            ->route('backoffice.products.show', [
                'cooperative' => $cooperativeId,
                'product' => $productModel->getKey(),
            ])
            ->with('success', 'Le produit a été réactivé avec succès.');
    }
}
