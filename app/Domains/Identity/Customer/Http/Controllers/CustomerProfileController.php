<?php

declare(strict_types=1);

namespace App\Domains\Identity\Customer\Http\Controllers;

use App\Domains\Identity\Customer\Http\Requests\UpdateCustomerProfileRequest;
use App\Domains\Identity\Customer\Services\CustomerProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class CustomerProfileController
{
    public function __construct(
        private readonly CustomerProfileService $profiles,
    ) {}

    public function edit(UpdateCustomerProfileRequest $request): View
    {
        return view('customer.profile.edit', [
            'user' => $request->user(),
        ]);
    }

    public function update(UpdateCustomerProfileRequest $request): RedirectResponse
    {
        $this->profiles->update(
            $request->user(),
            $request->validated(),
        );

        return redirect()
            ->route('customer.profile.edit')
            ->with('success', 'Votre profil a été mis à jour.');
    }
}
