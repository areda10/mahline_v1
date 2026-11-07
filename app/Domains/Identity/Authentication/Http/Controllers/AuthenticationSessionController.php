<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\Http\Controllers;

use App\Domains\Identity\Authentication\Models\AuthenticationSession;
use App\Domains\Identity\Authentication\Services\SessionService;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AuthenticationSessionController
{
    public function index(Request $request): View
    {
        abort_if($request->user()->hasRole('super_admin_root'), 404);

        return $this->sessionsView($request);
    }

    public function rootIndex(Request $request): View
    {
        abort_unless($request->user()->hasRole('super_admin_root'), 403);

        return $this->sessionsView($request);
    }

    public function revokeOwn(Request $request, AuthenticationSession $session, SessionService $service): RedirectResponse
    {
        $isRoot = $request->user()->hasRole('super_admin_root');
        $owned = $service->findActiveById($request->user(), (string) $session->getKey());
        abort_if($owned === null, 404);

        $service->revoke($owned, $isRoot ? 'root_revocation' : 'user_revocation');

        return redirect()
            ->route($isRoot ? 'root.backoffice.security.sessions.index' : 'backoffice.security.sessions.index')
            ->with('success', 'La session sélectionnée a été révoquée.');
    }

    public function revokeForUser(Request $request, string $user, string $session, SessionService $service): RedirectResponse
    {
        abort_unless($request->user()->hasRole('super_admin_root'), 403);

        $target = User::query()->findOrFail($user);
        $owned = $service->findActiveById($target, $session);
        abort_if($owned === null, 404);

        $service->revoke($owned, 'root_revocation');

        return redirect()->route('root.backoffice.users.show', $target)
            ->with('success', 'La session sélectionnée a été révoquée.');
    }

    private function sessionsView(Request $request): View
    {
        $sessions = AuthenticationSession::query()
            ->where('user_id', $request->user()->getKey())
            ->whereNull('revoked_at')
            ->orderByDesc('last_activity_at')
            ->get();

        $currentId = $request->session()->getId();
        $sessions->each(static function (AuthenticationSession $session) use ($currentId): void {
            $session->setAttribute('is_current', hash_equals((string) $session->session_id, $currentId));
        });

        return view('identity.security.sessions.index', ['sessions' => $sessions]);
    }
}
