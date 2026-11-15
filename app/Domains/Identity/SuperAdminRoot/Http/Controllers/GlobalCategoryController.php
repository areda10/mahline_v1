<?php

declare(strict_types=1);

namespace App\Domains\Identity\SuperAdminRoot\Http\Controllers;

use App\Domains\Categories\Models\Category;
use App\Domains\Categories\Repositories\CategoryRepository;
use App\Domains\Categories\Services\CategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class GlobalCategoryController
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
        private readonly CategoryService $categoryService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeRoot($request);
        Gate::authorize('viewAny', Category::class);

        return view('super-admin-root.categories.index', [
            'categories' => $this->categoryRepository->paginateGlobal(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizeRoot($request);
        Gate::authorize('create', Category::class);

        return view('super-admin-root.categories.create', [
            'category' => new Category,
            'categories' => Category::query()->global()->active()
                ->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeRoot($request);
        Gate::authorize('create', Category::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:180'],
            'description' => ['nullable', 'string'],
            'parent_id' => ['nullable', 'string', 'max:26'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $this->categoryService->createGlobal($data);

        return redirect()->route('root.backoffice.categories.index')
            ->with('success', 'La catégorie globale a été créée.');
    }

    public function edit(Request $request, string $category): View
    {
        $this->authorizeRoot($request);
        $categoryModel = $this->findGlobalCategory($category);
        Gate::authorize('update', $categoryModel);

        return view('super-admin-root.categories.edit', [
            'category' => $categoryModel,
            'categories' => Category::query()->global()->active()
                ->where('id', '!=', $categoryModel->getKey())
                ->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, string $category): RedirectResponse
    {
        $this->authorizeRoot($request);
        $categoryModel = $this->findGlobalCategory($category);
        Gate::authorize('update', $categoryModel);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:180'],
            'description' => ['sometimes', 'nullable', 'string'],
            'parent_id' => ['sometimes', 'nullable', 'string', 'max:26'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $this->categoryService->update($categoryModel, $data);

        return redirect()->route('root.backoffice.categories.index')
            ->with('success', 'La catégorie globale a été mise à jour.');
    }

    public function destroy(Request $request, string $category): RedirectResponse
    {
        $this->authorizeRoot($request);
        $categoryModel = $this->findGlobalCategory($category);
        Gate::authorize('delete', $categoryModel);

        $this->categoryService->delete($categoryModel);

        return redirect()->route('root.backoffice.categories.index')
            ->with('success', 'La branche globale a été supprimée selon les règles MAHLINE.');
    }

    private function findGlobalCategory(string $id): Category
    {
        return Category::query()->global()->findOrFail($id);
    }

    private function authorizeRoot(Request $request): void
    {
        abort_unless($request->user()?->hasRole('super_admin_root'), 403);
    }
}
