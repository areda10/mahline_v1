<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\Http\Controllers;

use App\Domains\Identity\Authentication\Models\PersonalAccessToken;
use App\Domains\Identity\Authentication\Services\ApiTokenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ApiTokenManagementController
{
    public function index(Request $request, ApiTokenService $service): View
    {
        return view('identity.security.api-tokens.index', [
            'tokens' => $service->tokensForUser($request->user()),
        ]);
    }

    public function store(Request $request, ApiTokenService $service): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:1', 'max:255'],
        ]);

        $newToken = $service->createForOwner(
            owner: $request->user(),
            actor: $request->user(),
            name: $validated['name'],
        );

        return redirect()
            ->route($this->routeName($request))
            ->with('new_api_token', $newToken->plainTextToken)
            ->with('success', 'La clé API a été créée. Copiez-la maintenant : elle ne sera plus affichée.');
    }

    public function destroy(
        Request $request,
        PersonalAccessToken $token,
        ApiTokenService $service,
    ): RedirectResponse {
        $revoked = $service->revokeToken(
            owner: $request->user(),
            token: $token,
            actor: $request->user(),
        );

        abort_unless($revoked, 404);

        return redirect()
            ->route($this->routeName($request))
            ->with('success', 'La clé API a été révoquée.');
    }

    public function destroyAll(Request $request, ApiTokenService $service): RedirectResponse
    {
        $count = $service->revokeForUser(
            user: $request->user(),
            actor: $request->user(),
            reason: 'owner_revoked_all',
        );

        return redirect()
            ->route($this->routeName($request))
            ->with('success', $count.' clé(s) API révoquée(s). Les sessions Web restent actives.');
    }

    private function routeName(Request $request): string
    {
        return $request->user()->hasRole('super_admin_root')
            ? 'root.backoffice.security.api-tokens.index'
            : 'backoffice.security.api-tokens.index';
    }
}
