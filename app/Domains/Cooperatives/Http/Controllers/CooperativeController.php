<?php

declare(strict_types=1);

namespace App\Domains\Cooperatives\Http\Controllers;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Cooperatives\Services\CooperativeService;
use App\Shared\Contracts\SuperAdminCooperativeAssignmentReader;
use App\Domains\Localization\Enums\MoroccanRegion;
use App\Domains\Localization\Contracts\CooperativeAddressManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class CooperativeController
{
    public function __construct(
        private readonly CooperativeService $cooperativeService,
        private readonly CooperativeAddressManager $addresses,
        private readonly SuperAdminCooperativeAssignmentReader $assignments,
    ) {}

    public function index(): View
    {
        Gate::authorize('viewAny', Cooperative::class);
        $cooperatives = Cooperative::query()->visibleTo(
            request()->user(),
            $this->assignments->cooperativeIdsFor((string) request()->user()->getKey()),
        )->with('address')->orderBy('name')->paginate(15);

        return view('backoffice.cooperatives.index', compact('cooperatives'));
    }

    public function create(): View
    {
        Gate::authorize('create', Cooperative::class);

        return view('backoffice.cooperatives.create', ['cooperative' => new Cooperative, 'regions' => MoroccanRegion::values()]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Cooperative::class);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'rib' => ['required', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:255'], 'number' => ['nullable', 'string', 'max:50'],
            'street' => ['required', 'string', 'max:255'], 'floor' => ['nullable', 'string', 'max:50'],
            'city' => ['required', 'string', 'max:120'], 'region' => ['nullable', 'string', Rule::in(MoroccanRegion::values())],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'phone' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'], 'rc' => ['nullable', 'string', 'max:255'],
            'ice' => ['required', 'string', 'max:255'],
            'country_id' => ['prohibited'],
        ]);
        $address = array_intersect_key($data, array_flip(['number', 'street', 'floor', 'postal_code', 'city', 'region']));
        $attributes = array_diff_key($data, $address);
        $cooperative = $this->cooperativeService->create((string) $request->user()->getKey(), $attributes, $address);

        return redirect()->route('backoffice.cooperatives.show', $cooperative)->with('success', 'La coopérative a été créée.');
    }

    public function show(string $cooperative): View
    {
        $model = $this->resolveCooperative($cooperative);

        return view('backoffice.cooperatives.show', ['cooperative' => $model->load('address.country')]);
    }

    public function edit(string $cooperative): View
    {
        $model = $this->resolveCooperative($cooperative);
        Gate::authorize('update', $model);

        return view('backoffice.cooperatives.edit', ['cooperative' => $model->load('address'), 'regions' => MoroccanRegion::values()]);
    }

    public function update(Request $request, string $cooperative): RedirectResponse
    {
        $model = $this->resolveCooperative($cooperative);
        Gate::authorize('update', $model);
        $actor = $request->user();
        $addressFields = ['number', 'street', 'floor', 'postal_code', 'city', 'region'];
        if ($actor->hasRole('cooperative_admin')) {
            abort_if(array_diff(array_keys($request->all()), ['phone', 'email', ...$addressFields, '_token', '_method']) !== [], 403);
        }
        $rules = [
            ...($actor->hasRole('cooperative_admin') ? [] : [
                'name' => ['sometimes', 'required', 'string', 'max:255'],
                'rib' => ['sometimes', 'required', 'string', 'max:255'],
                'tax_id' => ['sometimes', 'nullable', 'string', 'max:255'],
                'rc' => ['sometimes', 'nullable', 'string', 'max:255'],
                'ice' => ['sometimes', 'required', 'string', 'max:255'],
            ]),
            'phone' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255'],
            'number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'street' => ['sometimes', 'required', 'string', 'max:255'],
            'floor' => ['sometimes', 'nullable', 'string', 'max:50'],
            'city' => ['sometimes', 'required', 'string', 'max:120'],
            'region' => ['sometimes', 'nullable', 'string', Rule::in(MoroccanRegion::values())],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'country_id' => ['prohibited'],
        ];
        $data = $request->validate($rules);
        $address = array_intersect_key($data, array_flip($addressFields));
        $attributes = array_diff_key($data, $address);
        DB::transaction(function () use ($actor, $model, $attributes, $address): void {
            if ($attributes !== []) {
                $this->cooperativeService->update((string) $actor->getKey(), $model, $attributes);
            }
            if ($address !== []) {
                $this->addresses->updateAddressForCooperative((string) $actor->getKey(), (string) $model->getKey(), $address);
            }
        });

        return redirect()->route('backoffice.cooperatives.show', $model)->with('success', 'La coopérative a été mise à jour.');
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
        $this->cooperativeService->delete((string) $request->user()->getKey(), $model);

        return redirect()->route('backoffice.cooperatives.index')->with('success', 'La coopérative a été supprimée.');
    }

    private function changeStatus(Request $request, string $id, string $action): RedirectResponse
    {
        $model = $this->resolveCooperative($id);
        $this->cooperativeService->{$action}((string) $request->user()->getKey(), $model);

        return redirect()->route('backoffice.cooperatives.show', $model)->with('success', 'Le statut de la coopérative a été mis à jour.');
    }

    private function resolveCooperative(string $id): Cooperative
    {
        $model = Cooperative::query()->findOrFail($id);
        Gate::authorize('view', $model);

        return $model;
    }
}
