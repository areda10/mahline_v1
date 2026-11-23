<?php

declare(strict_types=1);

namespace App\Domains\Identity\Customer\Http\Controllers;

use App\Domains\Identity\Customer\Http\Requests\RequestCustomerEmailChange;
use App\Domains\Identity\Customer\Http\Requests\UpdateCustomerProfileRequest;
use App\Domains\Identity\Customer\Services\CustomerEmailChangeService;
use App\Domains\Identity\Customer\Services\CustomerProfileService;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Localization\Contracts\UserAddressLifecycleManager;
use App\Domains\Localization\Enums\MoroccanRegion;
use App\Domains\Localization\Models\Address;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class CustomerProfileController
{
    public function __construct(
        private readonly CustomerProfileService $profiles,
        private readonly CustomerEmailChangeService $emailChanges,
        private readonly UserAddressLifecycleManager $addresses,
    ) {}

    public function edit(Request $request): View
    {
        $customer = $request->user();

        abort_unless(
            $customer instanceof User
            && $customer->hasRole('customer')
            && ! $customer->hasAnyRole([
                'super_admin_root',
                'super_admin',
                'cooperative_admin',
                'user',
            ]),
            403,
        );

        $customer->load('primaryAddress.country');

        return view('customer.profile.edit', [
            'user' => $customer,
            'address' => $customer->primaryAddress,
            'addresses' => $customer->addresses,
            'regions' => MoroccanRegion::values(),
        ]);
    }

    public function update(UpdateCustomerProfileRequest $request): RedirectResponse
    {
        $customer = $request->user();

        abort_unless($customer instanceof User, 403);

        $validated = $request->validated();
        $addressAttributes = array_intersect_key($validated, array_flip([
            'number', 'street', 'floor', 'city', 'region', 'postal_code',
        ]));
        $profileAttributes = array_diff_key($validated, $addressAttributes);

        DB::transaction(function () use ($customer, $profileAttributes, $addressAttributes): void {
            $this->profiles->update($customer, $profileAttributes);

            if ($customer->addresses()->exists()) {
                $this->addresses->updateForUser((string) $customer->getKey(), (string) $customer->getKey(), $addressAttributes);
            } else {
                $this->addresses->createForUser((string) $customer->getKey(), (string) $customer->getKey(), $addressAttributes);
            }
        });

        return redirect()
            ->route('customer.profile.edit')
            ->with('success', 'Votre profil a été mis à jour.');
    }

    public function storeAddress(Request $request): RedirectResponse|JsonResponse
    {
        $customer = $request->user();
        abort_unless($customer instanceof User && $customer->hasRole('customer'), 403);
        $data = $request->validate([
            'number' => ['nullable', 'string', 'max:50'],
            'street' => ['required', 'string', 'max:255'],
            'floor' => ['nullable', 'string', 'max:50'],
            'city' => ['required', 'string', 'max:120'],
            'region' => ['nullable', 'string', 'in:'.implode(',', MoroccanRegion::values())],
            'postal_code' => ['nullable', 'string', 'max:20'],
        ]);
        $this->addresses->createForUser((string) $customer->getKey(), (string) $customer->getKey(), $data);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Adresse ajoutée à votre carnet.'], 201);
        }

        return redirect()->route('customer.profile.edit')->with('success', 'Adresse ajoutée à votre carnet.');
    }

    public function setPrimaryAddress(Request $request, Address $address): RedirectResponse
    {
        $customer = $request->user();
        abort_unless($customer instanceof User && $customer->hasRole('customer'), 403);
        $this->addresses->setPrimaryForUser((string) $customer->getKey(), (string) $customer->getKey(), (string) $address->getKey());

        return redirect()->route('customer.profile.edit')->with('success', 'Adresse principale mise à jour.');
    }

    public function deleteAddress(Request $request, Address $address): RedirectResponse
    {
        $customer = $request->user();
        abort_unless($customer instanceof User && $customer->hasRole('customer'), 403);
        $this->addresses->deleteForUser((string) $customer->getKey(), (string) $customer->getKey(), (string) $address->getKey());

        return redirect()->route('customer.profile.edit')->with('success', 'Adresse supprimée de votre carnet.');
    }

    public function requestEmailChange(RequestCustomerEmailChange $request): RedirectResponse
    {
        $customer = $request->user();

        abort_unless($customer instanceof User, 403);

        $this->emailChanges->requestChange(
            customer: $customer,
            newEmail: $request->validated('new_email'),
            password: $request->validated('current_password'),
        );

        return redirect()
            ->route('customer.profile.edit')
            ->with('success', 'Un lien de confirmation valable 30 minutes a été envoyé à votre nouvelle adresse e-mail.');
    }

    public function verifyEmailChange(Request $request): RedirectResponse
    {
        $verified = $this->emailChanges->verifyChange(
            (string) $request->query('token', ''),
        );

        if (! $verified) {
            return redirect()
                ->route('customer.login')
                ->withErrors([
                    'email' => 'Ce lien de changement d’adresse e-mail est invalide, expiré ou déjà utilisé.',
                ]);
        }

        return redirect()
            ->route('customer.login')
            ->with('status', 'Votre adresse e-mail a été modifiée. Toutes vos sessions ont été révoquées ; reconnectez-vous avec votre nouvelle adresse.');
    }
}
