<?php

declare(strict_types=1);

namespace App\Domains\Identity\SuperAdminRoot\Http\Controllers;

use App\Domains\Catalog\Models\Catalog;
use App\Domains\Categories\Models\Category;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Products\Models\Product;
use App\Domains\Products\Repositories\ProductRepository;
use App\Domains\Products\Services\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class ProductController
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly ProductService $productService,
    ) {}

    public function index(Request $request, string $cooperative): View
    {
        $this->rootOnly($request);
        $coop = $this->cooperative($cooperative);
        Gate::authorize('viewAny', Product::class);

        return view('super-admin-root.cooperatives.products.index', [
            'cooperative' => $coop,
            'products' => $this->productRepository->paginateForCooperative(
                (string) $coop->getKey(),
            ),
        ]);
    }

    public function create(Request $request, string $cooperative): View
    {
        $this->rootOnly($request);
        $coop = $this->cooperative($cooperative);
        $cooperativeId = (string) $coop->getKey();

        Gate::authorize('create', [Product::class, $cooperativeId]);

        return view('super-admin-root.cooperatives.products.create', [
            'cooperative' => $coop,
            'product' => new Product,
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

    public function store(Request $request, string $cooperative): RedirectResponse
    {
        $this->rootOnly($request);
        $coop = $this->cooperative($cooperative);
        $cooperativeId = (string) $coop->getKey();

        Gate::authorize('create', [Product::class, $cooperativeId]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'category_ids' => ['sometimes', 'array'],
            'category_ids.*' => ['required', 'string', 'max:26', 'distinct'],
            'catalog_ids' => ['sometimes', 'array'],
            'catalog_ids.*' => ['required', 'string', 'max:26', 'distinct'],
        ]);

        $data['category_ids'] ??= [];
        $data['catalog_ids'] ??= [];

        $product = $this->productService->create($cooperativeId, $data);

        return redirect()
            ->route('root.backoffice.cooperatives.products.show', [
                'cooperative' => $cooperativeId,
                'product' => $product->getKey(),
            ])
            ->with('success', 'Le produit a été créé.');
    }

    public function show(Request $request, string $cooperative, string $product): View
    {
        $this->rootOnly($request);
        $coop = $this->cooperative($cooperative);
        $item = $this->productRepository->findForCooperativeOrFail(
            $product,
            (string) $coop->getKey(),
        );
        Gate::authorize('view', $item);

        return view('super-admin-root.cooperatives.products.show', [
            'cooperative' => $coop,
            'product' => $item,
        ]);
    }

    public function edit(Request $request, string $cooperative, string $product): View
    {
        $this->rootOnly($request);
        $coop = $this->cooperative($cooperative);
        $cooperativeId = (string) $coop->getKey();
        $item = $this->productRepository->findForCooperativeOrFail($product, $cooperativeId);
        Gate::authorize('update', $item);

        return view('super-admin-root.cooperatives.products.edit', [
            'cooperative' => $coop,
            'product' => $item,
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

    public function update(Request $request, string $cooperative, string $product): RedirectResponse
    {
        $this->rootOnly($request);
        $coop = $this->cooperative($cooperative);
        $cooperativeId = (string) $coop->getKey();
        $item = $this->productRepository->findForCooperativeOrFail($product, $cooperativeId);
        Gate::authorize('update', $item);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:180'],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'category_ids' => ['sometimes', 'array'],
            'category_ids.*' => ['required', 'string', 'max:26', 'distinct'],
            'catalog_ids' => ['sometimes', 'array'],
            'catalog_ids.*' => ['required', 'string', 'max:26', 'distinct'],
        ]);

        $this->productService->update($item, $cooperativeId, $data);

        return redirect()
            ->route('root.backoffice.cooperatives.products.show', [
                'cooperative' => $cooperativeId,
                'product' => $item->getKey(),
            ])
            ->with('success', 'Le produit a été mis à jour.');
    }

    public function destroy(Request $request, string $cooperative, string $product): RedirectResponse
    {
        $this->rootOnly($request);
        $coop = $this->cooperative($cooperative);
        $cooperativeId = (string) $coop->getKey();
        $item = $this->productRepository->findForCooperativeOrFail($product, $cooperativeId);
        Gate::authorize('delete', $item);
        $this->productService->delete($item);

        return redirect()
            ->route('root.backoffice.cooperatives.products.index', [
                'cooperative' => $cooperativeId,
            ])
            ->with('success', 'Le produit a été supprimé.');
    }

    public function reactivate(Request $request, string $cooperative, string $product): RedirectResponse
    {
        $this->rootOnly($request);
        $coop = $this->cooperative($cooperative);
        $cooperativeId = (string) $coop->getKey();
        $item = $this->productRepository->findForCooperativeOrFail($product, $cooperativeId);
        Gate::authorize('reactivate', $item);
        $this->productService->reactivate($item);

        return redirect()
            ->route('root.backoffice.cooperatives.products.show', [
                'cooperative' => $cooperativeId,
                'product' => $item->getKey(),
            ])
            ->with('success', 'Le produit a été réactivé avec succès.');
    }

    private function rootOnly(Request $request): void
    {
        abort_unless($request->user()?->hasRole('super_admin_root'), 403);
    }

    private function cooperative(string $id): Cooperative
    {
        $coop = Cooperative::query()->findOrFail($id);
        Gate::authorize('view', $coop);

        return $coop;
    }
}
