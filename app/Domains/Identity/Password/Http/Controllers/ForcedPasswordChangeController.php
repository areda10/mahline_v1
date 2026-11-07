<?php

declare(strict_types=1);

namespace App\Domains\Identity\Password\Http\Controllers;

use App\Domains\Identity\Password\Rules\PasswordPolicy;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use InvalidArgumentException;

final class ForcedPasswordChangeController
{
    public function edit(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return redirect()->route('authentication.login');
        }

        if (! $user->must_change_password) {
            return $this->dashboardRedirect($user);
        }

        return view('auth.force-password-change');
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);
        abort_unless($user->must_change_password, 403);

        $validated = $request->validate([
            'password' => ['required', 'string', 'confirmed', 'min:12', 'max:128'],
            'password_confirmation' => ['required', 'string'],
        ]);

        try {
            PasswordPolicy::validate($validated['password']);
            PasswordPolicy::validateChange('MahlineMahline', $validated['password']);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['password' => $exception->getMessage()]);
        }

        DB::transaction(function () use ($user, $validated): void {
            $lockedUser = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            if (! $lockedUser->must_change_password || $lockedUser->status->value !== 'active') {
                abort(403);
            }

            $lockedUser->password = $validated['password'];
            $lockedUser->must_change_password = false;
            $lockedUser->save();
        });

        return $this->dashboardRedirect($user->fresh());
    }

    private function dashboardRedirect(User $user): RedirectResponse
    {
        if ($user->hasRole('super_admin_root')) {
            return redirect()->route('root.backoffice.dashboard');
        }

        return redirect()->route('backoffice.dashboard');
    }
}
