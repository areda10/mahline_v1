<?php

declare(strict_types=1);

namespace App\Domains\Identity\Password\Http\Controllers;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Identity\Password\Rules\PasswordPolicy;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use Illuminate\View\View;
use App\Shared\DTOs\AuditEntryDTO;
use InvalidArgumentException;

final class ForcedPasswordChangeController
{
    public function edit(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return redirect()->route('authentication.login');
        }

        if ($user->must_change_password) {
            return view('auth.force-password-change');
        }

        return view('identity.password.change');
    }

    public function update(Request $request, AuditService $audit): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $forced = (bool) $user->must_change_password;

        $rules = [
            'password' => ['required', 'string', 'confirmed', 'min:12', 'max:128'],
            'password_confirmation' => ['required', 'string'],
        ];

        if (! $forced) {
            $rules['current_password'] = ['required', 'string', 'current_password'];
        }

        $validated = $request->validate($rules);

        try {
            PasswordPolicy::validate($validated['password']);
            PasswordPolicy::validateChange(
                $forced ? 'MahlineMahline' : (string) $validated['current_password'],
                $validated['password'],
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['password' => $exception->getMessage()]);
        }

        DB::transaction(function () use ($user, $validated, $audit, $forced): void {
            $lockedUser = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedUser->status->value !== 'active') {
                abort(403);
            }

            if ($forced !== (bool) $lockedUser->must_change_password) {
                abort(403);
            }

            $lockedUser->password = $validated['password'];
            $lockedUser->must_change_password = false;
            $lockedUser->save();

            $audit->record(new AuditEntryDTO(
                eventKey: $forced
                    ? 'identity.user.forced_password_changed'
                    : 'identity.user.password_changed',
                action: $forced ? 'forced_password_changed' : 'password_changed',
                actorType: User::class,
                actorId: (string) $lockedUser->getKey(),
                cooperativeId: $lockedUser->cooperative_id ? (string) $lockedUser->cooperative_id : null,
                subjectType: User::class,
                subjectId: (string) $lockedUser->getKey(),
                oldValues: ['must_change_password' => true],
                newValues: ['must_change_password' => false],
            ));
        });

        return $this->dashboardRedirect($user->fresh());
    }

    private function dashboardRedirect(User $user): RedirectResponse
    {
        if ($user->hasRole('super_admin_root')) {
            return redirect()->route('root.backoffice.dashboard');
        }

        if ($user->hasRole('user')) {
            return redirect()->route('user.dashboard');
        }

        if ($user->hasAnyRole(['super_admin', 'cooperative_admin'])) {
            return redirect()->route('backoffice.dashboard');
        }

        abort(403, 'No dashboard is available for this account role.');
    }
}
