<?php

declare(strict_types=1);

namespace App\Domains\Identity\SuperAdminRoot\Http\Controllers;

use App\Domains\Identity\Authentication\Http\Resources\ApiTokenResource;
use App\Domains\Identity\Authentication\Models\PersonalAccessToken;
use App\Domains\Identity\Authentication\Services\ApiTokenService;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class UserApiTokenController
{
    public function index(User $user, ApiTokenService $service): JsonResponse
    {
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
