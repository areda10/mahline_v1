<?php

declare(strict_types=1);

namespace App\Domains\Categories\Http\Controllers;

use App\Domains\Categories\Models\Category;
use App\Domains\Categories\Repositories\CategoryRepository;
use App\Domains\Categories\Services\CategoryService;
use App\Domains\Cooperatives\Models\Cooperative;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class CategoryController
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
        private readonly CategoryService $categoryService,
    ) {
    }

    public function index(string $cooperative): View
    {
        $cooperativeModel = $this->resolveCooperative($cooperative);

        Gate::authorize('viewAny', Category::class);

        $categories = $this->categoryRepository->paginateAvailableFor(
            (string) $cooperativeModel->getKey(),
        );

        return view('backoffice.categories.index', [
            'cooperative' => $cooperativeModel,
            'categories' => $categories,
        ]);
    }

    public function create(string $cooperative): View
    {
        $cooperativeModel = $this->resolveCooperative($cooperative);

        Gate::authorize('create', Category::class);

        $categories = Category::query()
            ->availableFor((string) $cooperativeModel->getKey())
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('backoffice.categories.create', [
            'cooperative' => $cooperativeModel,
            'category' => new Category(),
            'categories' => $categories,
        ]);
    }

    public function store(
        Request $request,
        string $cooperative,
    ): RedirectResponse {
        $cooperativeModel = $this->resolveCooperative($cooperative);

        Gate::authorize('create', Category::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'parent_id' => ['nullable', 'string', 'max:26'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        // Cette route crée une catégorie locale, jamais une catégorie globale.
        $this->categoryService->createForCooperative(
            (string) $cooperativeModel->getKey(),
            $data,
        );

        return redirect()
            ->route('backoffice.categories.index', [
                'cooperative' => $cooperativeModel->getKey(),
            ])
            ->with('success', 'La catégorie a été créée.');
    }

    public function edit(
        string $cooperative,
        string $category,
    ): View {
        $cooperativeModel = $this->resolveCooperative($cooperative);

        $categoryModel = $this->categoryRepository
            ->findAvailableForOrFail(
                $category,
                (string) $cooperativeModel->getKey(),
            );

        Gate::authorize('update', $categoryModel);

        $categories = Category::query()
            ->availableFor((string) $cooperativeModel->getKey())
            ->active()
            ->where('id', '!=', $categoryModel->getKey())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('backoffice.categories.edit', [
            'cooperative' => $cooperativeModel,
            'category' => $categoryModel,
            'categories' => $categories,
        ]);
    }

    public function update(
        Request $request,
        string $cooperative,
        string $category,
    ): RedirectResponse {
        $cooperativeModel = $this->resolveCooperative($cooperative);

        $categoryModel = $this->categoryRepository
            ->findAvailableForOrFail(
                $category,
                (string) $cooperativeModel->getKey(),
            );

        Gate::authorize('update', $categoryModel);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'parent_id' => ['sometimes', 'nullable', 'string', 'max:26'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $this->categoryService->update($categoryModel, $data);

        return redirect()
            ->route('backoffice.categories.index', [
                'cooperative' => $cooperativeModel->getKey(),
            ])
            ->with('success', 'La catégorie a été mise à jour.');
    }

    public function destroy(
        string $cooperative,
        string $category,
    ): RedirectResponse {
        $cooperativeModel = $this->resolveCooperative($cooperative);

        $categoryModel = $this->categoryRepository
            ->findAvailableForOrFail(
                $category,
                (string) $cooperativeModel->getKey(),
            );

        Gate::authorize('delete', $categoryModel);

        $this->categoryService->delete($categoryModel);

        return redirect()
            ->route('backoffice.categories.index', [
                'cooperative' => $cooperativeModel->getKey(),
            ])
            ->with('success', 'La catégorie a été supprimée.');
    }

    private function resolveCooperative(string $id): Cooperative
    {
        $cooperative = Cooperative::query()->findOrFail($id);

        Gate::authorize('view', $cooperative);

        return $cooperative;
    }
}