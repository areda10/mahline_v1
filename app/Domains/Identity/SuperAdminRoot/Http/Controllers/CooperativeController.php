<?php

declare(strict_types=1);

namespace App\Domains\Identity\SuperAdminRoot\Http\Controllers;

use App\Domains\Cooperatives\Enums\CooperativeStatus;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Cooperatives\Services\CooperativeService;
use App\Domains\Localization\Contracts\CooperativeAddressManager;
use App\Domains\Localization\Enums\MoroccanRegion;
use App\Shared\Contracts\SuperAdminCooperativeAssignmentReader;
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

    public function index(Request $request): View
    {
        $this->authorizeRoot($request);
        Gate::authorize('viewAny', Cooperative::class);
        $cooperatives = Cooperative::query()->visibleTo(
            $request->user(),
            $this->assignments->cooperativeIdsFor((string) $request->user()->getKey()),
        )->with('address')->orderBy('name')->paginate(15);

        return view('super-admin-root.cooperatives.index', compact('cooperatives'));
    }

    public function create(Request $request): View
    {
        $this->authorizeRoot($request);
        Gate::authorize('create', Cooperative::class);

        return view('super-admin-root.cooperatives.create', ['cooperative' => new Cooperative, 'regions' => MoroccanRegion::values()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeRoot($request);
        Gate::authorize('create', Cooperative::class);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'rib' => ['required', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:255'], 'phone' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'], 'rc' => ['nullable', 'string', 'max:255'],
            'ice' => ['required', 'string', 'max:255'], 'country_id' => ['prohibited'], ...$this->addressRules(),
        ]);
        $addressData = array_intersect_key($data, array_flip(['number', 'street', 'floor', 'postal_code', 'city', 'region']));
        $cooperativeData = array_diff_key($data, $addressData);
        $cooperative = $this->cooperativeService->create((string) $request->user()->getKey(), $cooperativeData, $addressData);

        return redirect()->route('root.backoffice.cooperatives.show', $cooperative)->with('success', 'La coopérative a été créée.');
    }

    public function show(Request $request, string $cooperative): View
    {
        $this->authorizeRoot($request);

        return view('super-admin-root.cooperatives.show', ['cooperative' => $this->resolveCooperative($cooperative)->load('address.country')]);
    }

    public function edit(Request $request, string $cooperative): View
    {
        $this->authorizeRoot($request);
        $model = $this->resolveCooperative($cooperative);
        Gate::authorize('update', $model);

        return view('super-admin-root.cooperatives.edit', ['cooperative' => $model->load('address'), 'regions' => MoroccanRegion::values()]);
    }

    public function update(Request $request, string $cooperative): RedirectResponse
    {
        $this->authorizeRoot($request);
        $model = $this->resolveCooperative($cooperative);
        Gate::authorize('update', $model);
        $addressFields = ['number', 'street', 'floor', 'postal_code', 'city', 'region'];
        $allowed = ['phone', 'email', ...$addressFields, '_token', '_method'];
        if ($request->user()->hasRole('cooperative_admin')) {
            abort_if(array_diff(array_keys($request->all()), $allowed) !== [], 403);
        }
        $rules = [
            ...($request->user()->hasRole('cooperative_admin') ? [] : [
                'name' => ['sometimes', 'required', 'string', 'max:255'],
                'rib' => ['sometimes', 'required', 'string', 'max:255'],
                'tax_id' => ['sometimes', 'nullable', 'string', 'max:255'],
                'rc' => ['sometimes', 'nullable', 'string', 'max:255'],
                'ice' => ['sometimes', 'required', 'string', 'max:255'],
            ]),
            'phone' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255'],
            ...$this->addressRules('sometimes'),
            'status' => ['sometimes', 'required', Rule::in(array_map(static fn (CooperativeStatus $s): string => $s->value, CooperativeStatus::cases()))],
            'country_id' => ['prohibited'],
        ];
        $data = $request->validate($rules);
        $status = isset($data['status']) ? CooperativeStatus::from($data['status']) : null;
        unset($data['status']);
        $addressData = array_intersect_key($data, array_flip($addressFields));
        $cooperativeData = array_diff_key($data, array_flip($addressFields));
        try {
            DB::transaction(function () use ($request, $model, $addressData, $cooperativeData, $status): void {
                $this->cooperativeService->update((string) $request->user()->getKey(), $model, $cooperativeData);
                if ($addressData !== []) {
                    $this->addresses->updateAddressForCooperative((string) $request->user()->getKey(), (string) $model->getKey(), $addressData);
                }
                if ($status !== null && $status !== $model->fresh()->status) {
                    match ($status) {
                        CooperativeStatus::ACTIVE => $this->cooperativeService->activate((string) $request->user()->getKey(), $model),
                        CooperativeStatus::INACTIVE => $this->cooperativeService->deactivate((string) $request->user()->getKey(), $model),
                        CooperativeStatus::SUSPENDED => $this->cooperativeService->suspend((string) $request->user()->getKey(), $model),
                        CooperativeStatus::ARCHIVED => $this->cooperativeService->archive((string) $request->user()->getKey(), $model),
                        CooperativeStatus::PENDING => throw new \DomainException('Une coopérative ne peut pas revenir au statut en attente.'),
                    };
                }
            });
        } catch (\DomainException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->route('root.backoffice.cooperatives.show', $model)->with('success', 'La coopérative a été mise à jour.');
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
        $this->cooperativeService->delete((string) $request->user()->getKey(), $model);

        return redirect()->route('root.backoffice.cooperatives.index')->with('success', 'La coopérative a été supprimée.');
    }

    public function restoreDeleted(Request $request, string $cooperative): RedirectResponse
    {
        $this->authorizeRoot($request);
        $model = Cooperative::withTrashed()->findOrFail($cooperative);
        $this->cooperativeService->restore((string) $request->user()->getKey(), $model);

        return redirect()
            ->route('root.backoffice.cooperatives.index')
            ->with('success', 'La coopérative et son adresse ont été restaurées.');
    }

    private function changeStatus(Request $request, string $id, string $action): RedirectResponse
    {
        $model = $this->resolveCooperative($id);
        $this->cooperativeService->{$action}((string) $request->user()->getKey(), $model);

        return redirect()->route('root.backoffice.cooperatives.show', $model)->with('success', 'Le statut de la coopérative a été mis à jour.');
    }

    private function addressRules(string $presence = ''): array
    {
        $p = $presence === '' ? [] : [$presence];

        return [
            'number' => [...$p, 'nullable', 'string', 'max:50'],
            'street' => [...$p, 'required', 'string', 'max:255'],
            'floor' => [...$p, 'nullable', 'string', 'max:50'],
            'city' => [...$p, 'required', 'string', 'max:120'],
            'region' => [...$p, 'nullable', 'string', 'max:120', Rule::in(MoroccanRegion::values())],
            'postal_code' => [...$p, 'nullable', 'string', 'max:20'],
        ];
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
