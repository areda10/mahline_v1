<?php

declare(strict_types=1);

namespace App\Domains\Identity\Customer\Http\Controllers;

use App\Domains\Identity\Customer\Http\Requests\RequestCustomerEmailChange;
use App\Domains\Identity\Customer\Http\Requests\UpdateCustomerProfileRequest;
use App\Domains\Identity\Customer\Services\CustomerEmailChangeService;
use App\Domains\Identity\Customer\Services\CustomerProfileService;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class CustomerProfileController
{
    public function __construct(
        private readonly CustomerProfileService $profiles,
        private readonly CustomerEmailChangeService $emailChanges,
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

        return view('customer.profile.edit', [
            'user' => $customer,
        ]);
    }

    public function update(UpdateCustomerProfileRequest $request): RedirectResponse
    {
        $customer = $request->user();

        abort_unless($customer instanceof User, 403);

        $this->profiles->update(
            $customer,
            $request->validated(),
        );

        return redirect()
            ->route('customer.profile.edit')
            ->with('success', 'Votre profil a été mis à jour.');
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