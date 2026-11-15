<?php

declare(strict_types=1);

namespace App\Domains\Identity\SuperAdminRoot\Http\Controllers;

use App\Domains\Identity\Authentication\Http\Resources\ApiTokenResource;
use App\Domains\Identity\Authentication\Models\PersonalAccessToken;
use App\Domains\Identity\Authentication\Services\ApiTokenService;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class UserApiTokenController
{
    public function destroyFromBackOffice(
        Request $request,
        User $user,
        PersonalAccessToken $token,
        ApiTokenService $service,
    ): RedirectResponse {
        Gate::authorize('viewAny', User::class);

        $revoked = $service->revokeToken(
            owner: $user,
            token: $token,
            actor: $request->user(),
            reason: 'root_revocation',
            eventKey: 'identity.authentication.root_api_token_revoked',
            action: 'root_api_token_revoked',
        );

        abort_unless($revoked, 404);

        return redirect()->route('root.backoffice.users.show', $user)
            ->with('success', 'La clé API sélectionnée a été révoquée. Les sessions Web restent inchangées.');
    }

    public function destroyAllFromBackOffice(
        Request $request,
        User $user,
        ApiTokenService $service,
    ): RedirectResponse {
        Gate::authorize('viewAny', User::class);

        $count = $service->revokeForUser(
            user: $user,
            actor: $request->user(),
            reason: 'root_revoked_all',
            eventKey: 'identity.authentication.root_api_token_revoked',
            action: 'root_api_token_revoked',
        );

        return redirect()->route('root.backoffice.users.show', $user)
            ->with('success', $count.' clé(s) API révoquée(s). Les sessions Web restent inchangées.');
    }

    public function index(User $user, ApiTokenService $service): JsonResponse
    {
        Gate::authorize('viewAny', User::class);

        return ApiTokenResource::collection(
            $service->tokensForUser($user),
        )->response();
    }

    public function destroy(
        Request $request,
        User $user,
        PersonalAccessToken $token,
        ApiTokenService $service,
    ): JsonResponse {
        Gate::authorize('viewAny', User::class);

        $revoked = $service->revokeToken(
            owner: $user,
            token: $token,
            actor: $request->user(),
            reason: 'root_revocation',
            eventKey: 'identity.authentication.root_api_token_revoked',
            action: 'root_api_token_revoked',
        );

        abort_unless($revoked, 404);

        return response()->json([
            'message' => 'API token revoked.',
        ]);
    }

    public function destroyAll(
        Request $request,
        User $user,
        ApiTokenService $service,
    ): JsonResponse {
        Gate::authorize('viewAny', User::class);

        $count = $service->revokeForUser(
            user: $user,
            actor: $request->user(),
            reason: 'root_revoked_all',
            eventKey: 'identity.authentication.root_api_token_revoked',
            action: 'root_api_token_revoked',
        );

        return response()->json([
            'message' => 'All active API tokens have been revoked.',
            'revoked_count' => $count,
        ]);
    }
}
