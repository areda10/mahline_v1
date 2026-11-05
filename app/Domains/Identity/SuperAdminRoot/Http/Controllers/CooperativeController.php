<?php

declare(strict_types=1);

namespace App\Domains\Identity\SuperAdminRoot\Http\Controllers;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Cooperatives\Services\CooperativeService;
use App\Domains\Localization\Models\Address;
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

    public function index(Request $request): View
    {
        $this->authorizeRoot($request);
        Gate::authorize('viewAny', Cooperative::class);

        $cooperatives = Cooperative::query()
            ->visibleTo(request()->user())
            ->with('address')
            ->orderBy('name')
            ->paginate(15);

        return view('root.root.backoffice.cooperatives.index', compact('cooperatives'));
    }

    public function create(Request $request): View
    {
        $this->authorizeRoot($request);
        Gate::authorize('create', Cooperative::class);

        return view('root.root.backoffice.cooperatives.create', [
            'cooperative' => new Cooperative(),
            'addresses' => $this->availableAddresses(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeRoot($request);
        Gate::authorize('create', Cooperative::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'rib' => ['required', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:255'],
            'address_id' => ['required', 'string', 'size:26', 'exists:addresses,id'],
            'phone' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'rc' => ['nullable', 'string', 'max:255'],
            'ice' => ['required', 'string', 'max:255'],
        ]);

        $cooperative = $this->cooperativeService->create($request->user(), $data);

        return redirect()
            ->route('root.root.backoffice.cooperatives.show', $cooperative)
            ->with('success', 'La coopérative a été créée.');
    }

    public function show(Request $request, string $cooperative): View
    {
        $this->authorizeRoot($request);
        $model = $this->resolveCooperative($cooperative);
        return view('root.root.backoffice.cooperatives.show', ['cooperative' => $model->load('address.country')]);
    }

    public function edit(Request $request, string $cooperative): View
    {
        $this->authorizeRoot($request);
        $model = $this->resolveCooperative($cooperative);
        Gate::authorize('update', $model);

        return view('root.root.backoffice.cooperatives.edit', [
            'cooperative' => $model,
            'addresses' => $this->availableAddresses(),
        ]);
    }

    public function update(Request $request, string $cooperative): RedirectResponse
    {
        $this->authorizeRoot($request);
        $model = $this->resolveCooperative($cooperative);
        Gate::authorize('update', $model);

        $user = $request->user();

        if ($user->hasRole('cooperative_admin')) {
            $forbiddenFields = array_diff(
                array_keys($request->all()),
                ['phone', 'email', 'address_id', '_token', '_method'],
            );

            abort_if($forbiddenFields !== [], 403);
        }

        $rules = $user->hasRole('cooperative_admin')
            ? [
                'phone' => ['sometimes', 'required', 'string', 'max:255'],
                'email' => ['sometimes', 'required', 'email', 'max:255'],
                'address_id' => ['sometimes', 'required', 'string', 'size:26', 'exists:addresses,id'],
            ]
            : [
                'name' => ['sometimes', 'required', 'string', 'max:255'],
                'rib' => ['sometimes', 'required', 'string', 'max:255'],
                'tax_id' => ['sometimes', 'nullable', 'string', 'max:255'],
                'address_id' => ['sometimes', 'required', 'string', 'size:26', 'exists:addresses,id'],
                'phone' => ['sometimes', 'required', 'string', 'max:255'],
                'email' => ['sometimes', 'required', 'email', 'max:255'],
                'rc' => ['sometimes', 'nullable', 'string', 'max:255'],
                'ice' => ['sometimes', 'required', 'string', 'max:255'],
            ];

        $data = $request->validate($rules);
        $this->cooperativeService->update($user, $model, $data);

        return redirect()
            ->route('root.root.backoffice.cooperatives.show', $model)
            ->with('success', 'La coopérative a été mise à jour.');
    }

    public function activate(Request $request, string $cooperative): RedirectResponse
    {
        $this->authorizeRoot($request);
        return $this->changeStatus($request, $cooperative, 'activate');
    }

    public function deactivate(Request $request, string $cooperative): RedirectResponse
    {
        $this->authorizeRoot($request);
        return $this->changeStatus($request, $cooperative, 'deactivate');
    }

    public function suspend(Request $request, string $cooperative): RedirectResponse
    {
        $this->authorizeRoot($request);
        return $this->changeStatus($request, $cooperative, 'suspend');
    }

    public function archive(Request $request, string $cooperative): RedirectResponse
    {
        $this->authorizeRoot($request);
        return $this->changeStatus($request, $cooperative, 'archive');
    }

    public function destroy(Request $request, string $cooperative): RedirectResponse
    {
        $this->authorizeRoot($request);
        $model = Cooperative::query()->withTrashed()->findOrFail($cooperative);
        $this->cooperativeService->delete($request->user(), $model);

        return redirect()
            ->route('root.root.backoffice.cooperatives.index')
            ->with('success', 'La coopérative a été supprimée.');
    }

    private function changeStatus(Request $request, string $id, string $action): RedirectResponse
    {
        $model = $this->resolveCooperative($id);
        $this->cooperativeService->{$action}($request->user(), $model);

        return redirect()
            ->route('root.root.backoffice.cooperatives.show', $model)
            ->with('success', 'Le statut de la coopérative a été mis à jour.');
    }

    private function availableAddresses()
    {
        return Address::query()
            ->with('country')
            ->orderBy('city')
            ->orderBy('address_line_1')
            ->get();
    }

    private function authorizeRoot(Request $request): void
    {
        abort_unless($request->user()?->hasRole('super_admin_root'), 403);
    }

    private function resolveCooperative(string $id): Cooperative
    {
        $model = Cooperative::query()->findOrFail($id);
        Gate::authorize('view', $model);
        return $model;
    }
}
