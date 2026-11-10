<?php

declare(strict_types=1);

namespace App\Domains\Identity\Password\Http\Controllers;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Identity\Authentication\Services\SessionService;
use App\Domains\Identity\Password\Rules\PasswordPolicy;
use App\Domains\Identity\Password\Services\PasswordHistoryService;
use App\Domains\Identity\Users\Models\User;
use App\Shared\DTOs\AuditEntryDTO;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        return view('identity.password.change');
    }

    public function update(
        Request $request,
        AuditService $audit,
        PasswordHistoryService $passwordHistory,
        SessionService $sessions,
    ): RedirectResponse {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $forced = (bool) $user->must_change_password;
        $currentAuthenticationSessionId = $request->session()->get('authentication_session_id');

        $rules = [
            'password' => ['required', 'string', 'confirmed', 'min:12', 'max:128'],
            'password_confirmation' => ['required', 'string'],
        ];

        if (! $forced) {
            $rules['current_password'] = ['required', 'string', 'current_password'];
        }

        $validated = $request->validate($rules);

        try {
            DB::transaction(function () use (
                $user,
                $validated,
                $audit,
                $forced,
                $passwordHistory,
                $sessions,
                $currentAuthenticationSessionId,
            ): void {
                $lockedUser = User::query()
                    ->whereKey($user->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedUser->status->value !== 'active') {
                    abort(403);
                }

                if ($forced !== (bool) $lockedUser->must_change_password) {
                    abort(403);
                }

                // Les validations contextuelles sont effectuées après le verrouillage
                // afin d'utiliser les données utilisateur les plus récentes.
                PasswordPolicy::validate($validated['password'], $lockedUser);

                PasswordPolicy::validateChange(
                    $forced ? 'MahlineMahline' : (string) $validated['current_password'],
                    $validated['password'],
                    $lockedUser,
                );

                $passwordHistory->assertNotReused($lockedUser, $validated['password']);
                $passwordHistory->archiveCurrentPassword($lockedUser);

                $lockedUser->password = $validated['password'];
                $lockedUser->must_change_password = false;
                $lockedUser->save();

                if ($forced && is_string($currentAuthenticationSessionId)) {
                    $sessions->revokeOtherSessions(
                        $lockedUser,
                        $currentAuthenticationSessionId,
                        'password_changed',
                    );
                } else {
                    $sessions->revokeForUser($lockedUser, 'password_changed');
                }

                $sessions->revokeApiTokensForUser(
                    user: $lockedUser,
                    actor: $lockedUser,
                    reason: 'password_changed',
                );

                $audit->record(new AuditEntryDTO(
                    eventKey: $forced
                        ? 'identity.user.forced_password_changed'
                        : 'identity.user.password_changed',
                    action: $forced ? 'forced_password_changed' : 'password_changed',
                    actorType: User::class,
                    actorId: (string) $lockedUser->getKey(),
                    cooperativeId: $lockedUser->cooperative_id
                        ? (string) $lockedUser->cooperative_id
                        : null,
                    subjectType: User::class,
                    subjectId: (string) $lockedUser->getKey(),
                    oldValues: ['must_change_password' => $forced],
                    newValues: ['must_change_password' => false],
                ));
            });
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors([
                'password' => $exception->getMessage(),
            ]);
        }

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
