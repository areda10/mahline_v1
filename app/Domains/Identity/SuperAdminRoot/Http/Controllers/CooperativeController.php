<?php

declare(strict_types=1);

namespace App\Domains\Identity\SuperAdminRoot\Http\Controllers;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Cooperatives\Services\CooperativeService;
use App\Domains\Countries\Models\Country;
use App\Domains\Localization\Models\Address;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

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

        return view('super-admin-root.cooperatives.index', compact('cooperatives'));
    }

    public function create(Request $request): View
    {
        $this->authorizeRoot($request);
        Gate::authorize('create', Cooperative::class);

        return view('super-admin-root.cooperatives.create', [
            'cooperative' => new Cooperative(),
            'countries' => $this->availableCountries(),
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
            'phone' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'rc' => ['nullable', 'string', 'max:255'],
            'ice' => ['required', 'string', 'max:255'],
            ...$this->addressRules(),
        ]);

        $cooperative = DB::transaction(function () use ($request, $data): Cooperative {
            $address = $this->createAddress($request, $data);
            $cooperativeData = array_diff_key($data, array_flip([
                'address_line_1', 'city', 'country_id', 'postal_code', 'state',
            ]));

            return $this->cooperativeService->create($request->user(), [
                ...$cooperativeData,
                'address_id' => $address->getKey(),
            ]);
        });

        return redirect()
            ->route('root.backoffice.cooperatives.show', $cooperative)
            ->with('success', 'La coopérative a été créée.');
    }

    public function show(Request $request, string $cooperative): View
    {
        $this->authorizeRoot($request);
        $model = $this->resolveCooperative($cooperative);
        return view('super-admin-root.cooperatives.show', ['cooperative' => $model->load('address.country')]);
    }

    public function edit(Request $request, string $cooperative): View
    {
        $this->authorizeRoot($request);
        $model = $this->resolveCooperative($cooperative);
        Gate::authorize('update', $model);

        return view('super-admin-root.cooperatives.edit', [
            'cooperative' => $model->load('address'),
            'countries' => $this->availableCountries(),
        ]);
    }

    public function update(Request $request, string $cooperative): RedirectResponse
    {
        $this->authorizeRoot($request);
        $model = $this->resolveCooperative($cooperative);
        Gate::authorize('update', $model);

        $user = $request->user();

        $addressFields = ['address_line_1', 'city', 'country_id', 'postal_code', 'state'];

        if ($user->hasRole('cooperative_admin')) {
            $forbiddenFields = array_diff(
                array_keys($request->all()),
                ['phone', 'email', ...$addressFields, '_token', '_method'],
            );

            abort_if($forbiddenFields !== [], 403);
        }

        $rules = [
            ...($user->hasRole('cooperative_admin') ? [] : [
                'name' => ['sometimes', 'required', 'string', 'max:255'],
                'rib' => ['sometimes', 'required', 'string', 'max:255'],
                'tax_id' => ['sometimes', 'nullable', 'string', 'max:255'],
                'rc' => ['sometimes', 'nullable', 'string', 'max:255'],
                'ice' => ['sometimes', 'required', 'string', 'max:255'],
            ]),
            'phone' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255'],
            ...$this->addressRules('sometimes'),
        ];

        $data = $request->validate($rules);
        $addressData = array_intersect_key($data, array_flip($addressFields));
        $cooperativeData = array_diff_key($data, array_flip($addressFields));

        DB::transaction(function () use ($user, $model, $addressData, $cooperativeData): void {
            if ($addressData !== []) {
                $address = $model->address_id
                    ? Address::query()->findOrFail($model->address_id)
                    : new Address();

                $address->fill($addressData);
                if (! $address->exists) {
                    $address->created_by = (string) $user->getKey();
                } else {
                    $address->updated_by = (string) $user->getKey();
                }
                $address->save();
                $cooperativeData['address_id'] = $address->getKey();
            }

            $this->cooperativeService->update($user, $model, $cooperativeData);
        });

        return redirect()
            ->route('root.backoffice.cooperatives.show', $model)
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
            ->route('root.backoffice.cooperatives.index')
            ->with('success', 'La coopérative a été supprimée.');
    }

    private function changeStatus(Request $request, string $id, string $action): RedirectResponse
    {
        $model = $this->resolveCooperative($id);
        $this->cooperativeService->{$action}($request->user(), $model);

        return redirect()
            ->route('root.backoffice.cooperatives.show', $model)
            ->with('success', 'Le statut de la coopérative a été mis à jour.');
    }

    private function availableCountries()
    {
        return Country::query()->where('code', 'MA')->where('is_active', true)->orderBy('name')->get();
    }

    /** @return array<string, array<int, mixed>> */
    private function addressRules(string $presence = ''): array
    {
        $required = $presence === '' ? [] : [$presence];

        return [
            'address_line_1' => [...$required, 'required', 'string', 'max:255'],
            'city' => [...$required, 'required', 'string', 'max:255'],
            'country_id' => [...$required, 'required', 'string', 'size:26',
                Rule::exists('countries', 'id')->where('code', 'MA')->where('is_active', true)->whereNull('deleted_at')],
            'postal_code' => [...$required, 'required', 'string', 'max:32'],
            'state' => [...$required, 'required', 'string', 'max:255',
                Rule::in([
                    'Tanger-Tétouan-Al Hoceïma',
                    "L'Oriental",
                    'Fès-Meknès',
                    'Rabat-Salé-Kénitra',
                    'Béni Mellal-Khénifra',
                    'Casablanca-Settat',
                    'Marrakech-Safi',
                    'Drâa-Tafilalet',
                    'Souss-Massa',
                    'Guelmim-Oued Noun',
                    'Laâyoune-Sakia El Hamra',
                    'Dakhla-Oued Ed-Dahab',
                ])],
        ];
    }

    /** @param array<string, mixed> $data */
    private function createAddress(Request $request, array $data): Address
    {
        $address = new Address();
        $address->fill([
            'address_line_1' => $data['address_line_1'],
            'city' => $data['city'],
            'country_id' => $data['country_id'],
            'postal_code' => $data['postal_code'],
            'state' => $data['state'],
        ]);
        $address->created_by = (string) $request->user()->getKey();
        $address->save();

        return $address;
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
