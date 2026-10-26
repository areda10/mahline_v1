<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Http\Controllers;

use App\Domains\Catalog\Models\Catalog;
use App\Domains\Catalog\Services\CatalogService;
use App\Domains\Cooperatives\Models\Cooperative;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class CatalogController
{
    public function __construct(
        private readonly CatalogService $catalogService,
    ) {
    }

    public function index(string $cooperative): View
    {
        $cooperativeModel = $this->resolveCooperative($cooperative);

        Gate::authorize('viewAny', Catalog::class);

        $catalogs = $this->catalogService->paginateForCooperative(
            (string) $cooperativeModel->getKey(),
        );

        return view('backoffice.catalogs.index', [
            'cooperative' => $cooperativeModel,
            'catalogs' => $catalogs,
        ]);
    }

    public function create(string $cooperative): View
    {
        $cooperativeModel = $this->resolveCooperative($cooperative);

        Gate::authorize('create', [
            Catalog::class,
            (string) $cooperativeModel->getKey(),
        ]);

        return view('backoffice.catalogs.create', [
            'cooperative' => $cooperativeModel,
            'catalog' => new Catalog(),
        ]);
    }

    public function store(
        Request $request,
        string $cooperative,
    ): RedirectResponse {
        $cooperativeModel = $this->resolveCooperative($cooperative);

        Gate::authorize('create', [
            Catalog::class,
            (string) $cooperativeModel->getKey(),
        ]);

        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:180'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $this->catalogService->createForCooperative(
            (string) $cooperativeModel->getKey(),
            $attributes,
        );

        return redirect()
            ->route('backoffice.catalogs.index', [
                'cooperative' => $cooperativeModel->getKey(),
            ])
            ->with('success', 'Le catalogue a été créé.');
    }

    public function show(
        string $cooperative,
        string $catalog,
    ): View {
        $cooperativeModel = $this->resolveCooperative($cooperative);

        $catalogModel = $this->catalogService
            ->findForCooperativeOrFail(
                $catalog,
                (string) $cooperativeModel->getKey(),
            );

        Gate::authorize('view', $catalogModel);

        return view('backoffice.catalogs.show', [
            'cooperative' => $cooperativeModel,
            'catalog' => $catalogModel,
        ]);
    }

    public function edit(
        string $cooperative,
        string $catalog,
    ): View {
        $cooperativeModel = $this->resolveCooperative($cooperative);

        $catalogModel = $this->catalogService
            ->findForCooperativeOrFail(
                $catalog,
                (string) $cooperativeModel->getKey(),
            );

        Gate::authorize('update', $catalogModel);

        return view('backoffice.catalogs.edit', [
            'cooperative' => $cooperativeModel,
            'catalog' => $catalogModel,
        ]);
    }

    public function update(
        Request $request,
        string $cooperative,
        string $catalog,
    ): RedirectResponse {
        $cooperativeModel = $this->resolveCooperative($cooperative);

        $catalogModel = $this->catalogService
            ->findForCooperativeOrFail(
                $catalog,
                (string) $cooperativeModel->getKey(),
            );

        Gate::authorize('update', $catalogModel);

        $attributes = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:180'],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $this->catalogService->update(
            $catalogModel,
            (string) $cooperativeModel->getKey(),
            $attributes,
        );

        return redirect()
            ->route('backoffice.catalogs.index', [
                'cooperative' => $cooperativeModel->getKey(),
            ])
            ->with('success', 'Le catalogue a été mis à jour.');
    }

    public function destroy(
        string $cooperative,
        string $catalog,
    ): RedirectResponse {
        $cooperativeModel = $this->resolveCooperative($cooperative);

        $catalogModel = $this->catalogService
            ->findForCooperativeOrFail(
                $catalog,
                (string) $cooperativeModel->getKey(),
            );

        Gate::authorize('delete', $catalogModel);

        $this->catalogService->delete(
            $catalogModel,
            (string) $cooperativeModel->getKey(),
        );

        return redirect()
            ->route('backoffice.catalogs.index', [
                'cooperative' => $cooperativeModel->getKey(),
            ])
            ->with('success', 'Le catalogue a été supprimé.');
    }

    private function resolveCooperative(string $id): Cooperative
    {
        $cooperative = Cooperative::query()->findOrFail($id);

        // Vérifie l'accès à cette coopérative via sa Policy existante.
        Gate::authorize('view', $cooperative);

        return $cooperative;
    }
}