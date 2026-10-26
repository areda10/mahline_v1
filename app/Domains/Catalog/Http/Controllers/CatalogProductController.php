<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Http\Controllers;

use App\Domains\Catalog\Services\CatalogService;
use App\Domains\Cooperatives\Models\Cooperative;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class CatalogProductController
{
    public function __construct(
        private readonly CatalogService $catalogService,
    ) {
    }

    public function store(
        Request $request,
        string $cooperative,
        string $catalog,
    ): RedirectResponse {
        $cooperativeModel = $this->resolveCooperative($cooperative);
        $cooperativeId = (string) $cooperativeModel->getKey();

        $catalogModel = $this->catalogService
            ->findForCooperativeOrFail($catalog, $cooperativeId);

        Gate::authorize('update', $catalogModel);

        $data = $request->validate([
            'product_id' => ['required', 'string', 'max:26'],
        ]);

        $this->catalogService->addProduct(
            $catalogModel,
            $cooperativeId,
            $data['product_id'],
        );

        return redirect()
            ->route('backoffice.catalogs.show', [
                'cooperative' => $cooperativeId,
                'catalog' => $catalogModel->getKey(),
            ])
            ->with('success', 'Le produit a été ajouté au catalogue.');
    }

    public function destroy(
        string $cooperative,
        string $catalog,
        string $product,
    ): RedirectResponse {
        $cooperativeModel = $this->resolveCooperative($cooperative);
        $cooperativeId = (string) $cooperativeModel->getKey();

        $catalogModel = $this->catalogService
            ->findForCooperativeOrFail($catalog, $cooperativeId);

        Gate::authorize('update', $catalogModel);

        $this->catalogService->removeProduct(
            $catalogModel,
            $cooperativeId,
            $product,
        );

        return redirect()
            ->route('backoffice.catalogs.show', [
                'cooperative' => $cooperativeId,
                'catalog' => $catalogModel->getKey(),
            ])
            ->with('success', 'Le produit a été retiré du catalogue.');
    }

    private function resolveCooperative(string $id): Cooperative
    {
        $cooperative = Cooperative::query()->findOrFail($id);

        Gate::authorize('view', $cooperative);

        return $cooperative;
    }
}