<?php

declare(strict_types=1);

namespace App\Domains\Identity\SuperAdminRoot\Http\Controllers;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Products\Models\Product;
use App\Domains\Products\Models\ProductUnit;
use App\Domains\Products\Models\ProductVariant;
use App\Domains\Products\Repositories\ProductRepository;
use App\Domains\Products\Repositories\ProductVariantRepository;
use App\Domains\Products\Services\ProductVariantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class ProductVariantController
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProductVariantRepository $variants,
        private readonly ProductVariantService $service,
    ) {}

    public function create(Request $request, string $cooperative, string $product): View
    {
        [$coop, $parent] = $this->resolveParent($request, $cooperative, $product);
        Gate::authorize('update', $parent);
        $this->assertVariantProduct($parent);

        return view('super-admin-root.cooperatives.products.variants.create', [
            'cooperative' => $coop,
            'product' => $parent,
            'variant' => new ProductVariant,
            'productUnits' => $this->units(),
        ]);
    }

    public function store(Request $request, string $cooperative, string $product): RedirectResponse
    {
        [$coop, $parent] = $this->resolveParent($request, $cooperative, $product);
        Gate::authorize('update', $parent);
        $this->assertVariantProduct($parent);

        $data = $request->validate($this->rules());
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $this->service->create($parent, $data, $request->user());

        return redirect()->route('root.backoffice.cooperatives.products.show', [
            'cooperative' => $coop->getKey(),
            'product' => $parent->getKey(),
        ])->with('success', 'La variante a été créée.');
    }

    public function edit(Request $request, string $cooperative, string $product, string $variant): View
    {
        [$coop, $parent, $item] = $this->resolveVariant($request, $cooperative, $product, $variant);
        Gate::authorize('update', $parent);

        return view('super-admin-root.cooperatives.products.variants.edit', [
            'cooperative' => $coop,
            'product' => $parent,
            'variant' => $item,
            'productUnits' => $this->units((string) $item->unit_id),
        ]);
    }

    public function update(Request $request, string $cooperative, string $product, string $variant): RedirectResponse
    {
        [$coop, $parent, $item] = $this->resolveVariant($request, $cooperative, $product, $variant);
        Gate::authorize('update', $parent);

        $data = $request->validate($this->rules());
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $this->service->update($item, $data, $request->user());

        return redirect()->route('root.backoffice.cooperatives.products.show', [
            'cooperative' => $coop->getKey(),
            'product' => $parent->getKey(),
        ])->with('success', 'La variante a été mise à jour.');
    }

    public function activate(Request $request, string $cooperative, string $product, string $variant): RedirectResponse
    {
        [$coop, $parent, $item] = $this->resolveVariant($request, $cooperative, $product, $variant);
        Gate::authorize('update', $parent);
        $this->service->activate($item, $request->user());

        return $this->backToProduct($coop, $parent, 'La variante a été activée.');
    }

    public function deactivate(Request $request, string $cooperative, string $product, string $variant): RedirectResponse
    {
        [$coop, $parent, $item] = $this->resolveVariant($request, $cooperative, $product, $variant);
        Gate::authorize('update', $parent);
        $this->service->deactivate($item, $request->user());

        return $this->backToProduct($coop, $parent, 'La variante a été désactivée.');
    }

    public function destroy(Request $request, string $cooperative, string $product, string $variant): RedirectResponse
    {
        [$coop, $parent, $item] = $this->resolveVariant($request, $cooperative, $product, $variant);
        Gate::authorize('delete', $parent);
        $this->service->delete($item, $request->user());

        return $this->backToProduct($coop, $parent, 'La variante a été supprimée.');
    }

    public function restore(Request $request, string $cooperative, string $product, string $variant): RedirectResponse
    {
        $this->rootOnly($request);
        $coop = $this->cooperative($cooperative);
        $parent = $this->products->findForCooperativeOrFail($product, (string) $coop->getKey());
        $item = $this->variants->findTrashedForProductOrFail($variant, (string) $parent->getKey());
        Gate::authorize('restore', $parent);
        $this->service->restore($item, $request->user());

        return $this->backToProduct($coop, $parent, 'La variante a été restaurée.');
    }

    /** @return array{0: Cooperative, 1: Product} */
    private function resolveParent(Request $request, string $cooperative, string $product): array
    {
        $this->rootOnly($request);
        $coop = $this->cooperative($cooperative);
        $parent = $this->products->findForCooperativeOrFail($product, (string) $coop->getKey());

        return [$coop, $parent];
    }

    /** @return array{0: Cooperative, 1: Product, 2: ProductVariant} */
    private function resolveVariant(Request $request, string $cooperative, string $product, string $variant): array
    {
        [$coop, $parent] = $this->resolveParent($request, $cooperative, $product);
        $item = $this->variants->findForProductOrFail($variant, (string) $parent->getKey());

        return [$coop, $parent, $item];
    }

    private function cooperative(string $id): Cooperative
    {
        $coop = Cooperative::query()->findOrFail($id);
        Gate::authorize('view', $coop);

        return $coop;
    }

    private function rootOnly(Request $request): void
    {
        abort_unless($request->user()?->hasRole('super_admin_root'), 403);
    }

    private function assertVariantProduct(Product $product): void
    {
        abort_unless(($product->product_type ?? 'simple') === 'variant', 422, 'Ce produit n’a pas été créé avec des variantes.');
    }

    /** @return array<string, mixed> */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180'],
            'price' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'unit_id' => [
                'required', 'string', 'size:26',
                // ProductVariantService validates active units and permits only
                // the currently assigned unit when it has since been disabled.
            ],
            'sku' => ['nullable', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function units(?string $currentUnitId = null)
    {
        return ProductUnit::query()->whereIn('code', ProductUnit::V1_CODES)
            ->where(function ($query) use ($currentUnitId): void {
                $query->where('is_active', true);
                if ($currentUnitId !== null) {
                    $query->orWhere('id', $currentUnitId);
                }
            })->orderBy('sort_order')->orderBy('label')->get();
    }

    private function backToProduct(Cooperative $coop, Product $product, string $message): RedirectResponse
    {
        return redirect()->route('root.backoffice.cooperatives.products.show', [
            'cooperative' => $coop->getKey(),
            'product' => $product->getKey(),
        ])->with('success', $message);
    }
}
