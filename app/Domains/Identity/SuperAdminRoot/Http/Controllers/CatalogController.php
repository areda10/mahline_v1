<?php

declare(strict_types=1);

namespace App\Domains\Identity\SuperAdminRoot\Http\Controllers;

use App\Domains\Catalog\Models\Catalog;
use App\Domains\Catalog\Services\CatalogService;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Products\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class CatalogController
{
    public function __construct(private readonly CatalogService $service) {}

    public function index(Request $request, string $cooperative): View
    {
        $this->rootOnly($request);
        $coop = $this->cooperative($cooperative);
        Gate::authorize('viewAny', Catalog::class);

        return view('super-admin-root.cooperatives.catalogs.index', [
            'cooperative' => $coop,
            'catalogs' => $this->service->paginateForCooperative((string) $coop->getKey()),
        ]);
    }

    public function create(Request $request, string $cooperative): View
    {
        $this->rootOnly($request);
        $coop = $this->cooperative($cooperative);
        Gate::authorize('create', [Catalog::class, (string) $coop->getKey()]);

        return view('super-admin-root.cooperatives.catalogs.create', [
            'cooperative' => $coop, 'catalog' => new Catalog,
        ]);
    }

    public function store(Request $request, string $cooperative): RedirectResponse
    {
        $this->rootOnly($request);
        $coop = $this->cooperative($cooperative);
        Gate::authorize('create', [Catalog::class, (string) $coop->getKey()]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:180'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $this->service->createForCooperative((string) $coop->getKey(), $data);

        return redirect()->route('root.backoffice.cooperatives.catalogs.index', ['cooperative' => $coop->getKey()])
            ->with('success', 'Le catalogue a été créé.');
    }

    public function show(Request $request, string $cooperative, string $catalog): View
    {
        $this->rootOnly($request);
        $coop = $this->cooperative($cooperative);
        $item = $this->service->findForCooperativeOrFail($catalog, (string) $coop->getKey());
        Gate::authorize('view', $item);

        $availableProducts = Product::query()
            ->where('cooperative_id', $coop->getKey())
            ->where('is_active', true)
            ->whereDoesntHave('catalogs', fn ($query) => $query->whereKey($item->getKey()))
            ->orderBy('name')
            ->get();

        return view('super-admin-root.cooperatives.catalogs.show', [
            'cooperative' => $coop,
            'catalog' => $item,
            'availableProducts' => $availableProducts,
        ]);
    }

    public function edit(Request $request, string $cooperative, string $catalog): View
    {
        $this->rootOnly($request);
        $coop = $this->cooperative($cooperative);
        $item = $this->service->findForCooperativeOrFail($catalog, (string) $coop->getKey());
        Gate::authorize('update', $item);

        return view('super-admin-root.cooperatives.catalogs.edit', ['cooperative' => $coop, 'catalog' => $item]);
    }

    public function update(Request $request, string $cooperative, string $catalog): RedirectResponse
    {
        $this->rootOnly($request);
        $coop = $this->cooperative($cooperative);
        $item = $this->service->findForCooperativeOrFail($catalog, (string) $coop->getKey());
        Gate::authorize('update', $item);
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:180'],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $this->service->update($item, (string) $coop->getKey(), $data);

        return redirect()->route('root.backoffice.cooperatives.catalogs.index', ['cooperative' => $coop->getKey()])
            ->with('success', 'Le catalogue a été mis à jour.');
    }

    public function destroy(Request $request, string $cooperative, string $catalog): RedirectResponse
    {
        $this->rootOnly($request);
        $coop = $this->cooperative($cooperative);
        $item = $this->service->findForCooperativeOrFail($catalog, (string) $coop->getKey());
        Gate::authorize('delete', $item);
        $this->service->delete($item, (string) $coop->getKey());

        return redirect()->route('root.backoffice.cooperatives.catalogs.index', ['cooperative' => $coop->getKey()])
            ->with('success', 'Le catalogue a été supprimé.');
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
