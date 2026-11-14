<?php

declare(strict_types=1);

namespace App\Domains\Identity\Customer\Http\Controllers;

use App\Domains\Identity\Customer\Http\Requests\UpdateCustomerProfileRequest;
use App\Domains\Identity\Customer\Services\CustomerProfileService;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class CustomerProfileController
{
    public function __construct(
        private readonly CustomerProfileService $profiles,
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
}
