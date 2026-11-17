<?php

declare(strict_types=1);

namespace App\Domains\Identity\Users\Http\Controllers;

use App\Domains\Identity\Users\Models\User;
use App\Domains\Localization\Enums\MoroccanRegion;
use App\Domains\Localization\Services\AddressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PersonalAddressController
{
    public function edit(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $user->load('primaryAddress.country');

        return view('identity.profile.address', [
            'user' => $user,
            'address' => $user->primaryAddress,
            'regions' => MoroccanRegion::values(),
            'layout' => $user->hasRole('super_admin_root') ? 'layouts.root' : 'layouts.admin',
        ]);
    }

    public function update(Request $request, AddressService $addresses): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $data = $request->validate([
            'number' => ['nullable', 'string', 'max:50'],
            'street' => ['required', 'string', 'max:255'],
            'floor' => ['nullable', 'string', 'max:50'],
            'city' => ['required', 'string', 'max:120'],
            'region' => ['required', 'string', 'in:'.implode(',', MoroccanRegion::values())],
            'postal_code' => ['nullable', 'string', 'max:20'],
        ]);

        if ($user->addresses()->exists()) {
            $addresses->updateForUser($user, $user, $data);
        } else {
            $addresses->createForUser($user, $user, $data);
        }

        return redirect()
            ->route($user->hasRole('super_admin_root') ? 'root.backoffice.profile.address.edit' : 'backoffice.profile.address.edit')
            ->with('success', 'Votre adresse personnelle a été mise à jour.');
    }

    public function updateUser(Request $request, User $user, AddressService $addresses): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $data = $request->validate([
            'number' => ['nullable', 'string', 'max:50'],
            'street' => ['required', 'string', 'max:255'],
            'floor' => ['nullable', 'string', 'max:50'],
            'city' => ['required', 'string', 'max:120'],
            'region' => ['required', 'string', 'in:'.implode(',', MoroccanRegion::values())],
            'postal_code' => ['nullable', 'string', 'max:20'],
        ]);

        if ($user->addresses()->exists()) {
            $addresses->updateForUser($actor, $user, $data);
        } else {
            $addresses->createForUser($actor, $user, $data);
        }

        return back()->with('success', 'L’adresse personnelle de cet utilisateur a été mise à jour.');
    }
}
