<?php

declare(strict_types=1);

namespace App\Domains\Cooperatives\Http\Controllers;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Cooperatives\Services\CooperativeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class CooperativeController
{
    public function __construct(
        private readonly CooperativeService $cooperativeService,
    ) {
    }

    public function index(): View
    {
        Gate::authorize('viewAny', Cooperative::class);

        $cooperatives = Cooperative::query()
            ->visibleTo(request()->user())
            ->with('address')
            ->orderBy('name')
            ->paginate(15);

        return view('backoffice.cooperatives.index', compact('cooperatives'));
    }

    public function create(): View
    {
        Gate::authorize('create', Cooperative::class);

        return view('backoffice.cooperatives.create', [
            'cooperative' => new Cooperative(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Cooperative::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'rib' => ['nullable', 'string', 'max:34'],
            'tax_id' => ['nullable', 'string', 'max:100'],
            'address_id' => ['nullable', 'string', 'max:26', 'exists:addresses,id'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:180'],
            'rc' => ['nullable', 'string', 'max:100'],
            'ice' => ['nullable', 'string', 'max:100'],
        ]);

        $cooperative = $this->cooperativeService->create($request->user(), $data);

        return redirect()
            ->route('backoffice.cooperatives.show', $cooperative)
            ->with('success', 'La coopérative a été créée.');
    }

    public function show(string $cooperative): View
    {
        $model = $this->resolveCooperative($cooperative);
        return view('backoffice.cooperatives.show', ['cooperative' => $model->load('address')]);
    }

    public function edit(string $cooperative): View
    {
        $model = $this->resolveCooperative($cooperative);
        Gate::authorize('update', $model);

        return view('backoffice.cooperatives.edit', ['cooperative' => $model]);
    }

    public function update(Request $request, string $cooperative): RedirectResponse
    {
        $model = $this->resolveCooperative($cooperative);
        Gate::authorize('update', $model);

        $user = $request->user();
        $rules = $user->hasRole('cooperative_admin')
            ? [
                'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
                'email' => ['sometimes', 'nullable', 'email', 'max:180'],
                'address_id' => ['sometimes', 'nullable', 'string', 'max:26', 'exists:addresses,id'],
            ]
            : [
                'name' => ['sometimes', 'required', 'string', 'max:180'],
                'rib' => ['sometimes', 'nullable', 'string', 'max:34'],
                'tax_id' => ['sometimes', 'nullable', 'string', 'max:100'],
                'address_id' => ['sometimes', 'nullable', 'string', 'max:26', 'exists:addresses,id'],
                'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
                'email' => ['sometimes', 'nullable', 'email', 'max:180'],
                'rc' => ['sometimes', 'nullable', 'string', 'max:100'],
                'ice' => ['sometimes', 'nullable', 'string', 'max:100'],
            ];

        $data = $request->validate($rules);
        $this->cooperativeService->update($user, $model, $data);

        return redirect()
            ->route('backoffice.cooperatives.show', $model)
            ->with('success', 'La coopérative a été mise à jour.');
    }

    public function activate(Request $request, string $cooperative): RedirectResponse
    {
        return $this->changeStatus($request, $cooperative, 'activate');
    }

    public function deactivate(Request $request, string $cooperative): RedirectResponse
    {
        return $this->changeStatus($request, $cooperative, 'deactivate');
    }

    public function suspend(Request $request, string $cooperative): RedirectResponse
    {
        return $this->changeStatus($request, $cooperative, 'suspend');
    }

    public function archive(Request $request, string $cooperative): RedirectResponse
    {
        return $this->changeStatus($request, $cooperative, 'archive');
    }

    public function destroy(Request $request, string $cooperative): RedirectResponse
    {
        $model = Cooperative::query()->withTrashed()->findOrFail($cooperative);
        $this->cooperativeService->delete($request->user(), $model);

        return redirect()
            ->route('backoffice.cooperatives.index')
            ->with('success', 'La coopérative a été supprimée.');
    }

    private function changeStatus(Request $request, string $id, string $action): RedirectResponse
    {
        $model = $this->resolveCooperative($id);
        $this->cooperativeService->{$action}($request->user(), $model);

        return redirect()
            ->route('backoffice.cooperatives.show', $model)
            ->with('success', 'Le statut de la coopérative a été mis à jour.');
    }

    private function resolveCooperative(string $id): Cooperative
    {
        $model = Cooperative::query()->findOrFail($id);
        Gate::authorize('view', $model);
        return $model;
    }
}
