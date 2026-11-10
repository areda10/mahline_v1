<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\Http\Controllers;

use App\Domains\Identity\Authentication\Http\Requests\StoreApiTokenRequest;
use App\Domains\Identity\Authentication\Http\Resources\ApiTokenResource;
use App\Domains\Identity\Authentication\Models\PersonalAccessToken;
use App\Domains\Identity\Authentication\Services\ApiTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ApiTokenController
{
    public function index(Request $request, ApiTokenService $service): JsonResponse
    {
        $tokens = $service->tokensForUser($request->user());

        return ApiTokenResource::collection($tokens)->response();
    }

    public function store(
        StoreApiTokenRequest $request,
        ApiTokenService $service,
    ): JsonResponse {
        $newToken = $service->createForOwner(
            owner: $request->user(),
            actor: $request->user(),
            name: $request->validated('name'),
        );

        return response()->json([
            'data' => (new ApiTokenResource($newToken->accessToken))->resolve(),
            'plain_text_token' => $newToken->plainTextToken,
            'message' => 'Copy this token now. It will not be shown again.',
        ], 201);
    }

    public function destroy(
        Request $request,
        PersonalAccessToken $token,
        ApiTokenService $service,
    ): JsonResponse {
        $revoked = $service->revokeToken(
            owner: $request->user(),
            token: $token,
            actor: $request->user(),
        );

        abort_unless($revoked, 404);

        return response()->json([
            'message' => 'API token revoked.',
        ]);
    }

    public function destroyAll(Request $request, ApiTokenService $service): JsonResponse
    {
        $count = $service->revokeForUser(
            user: $request->user(),
            actor: $request->user(),
            reason: 'owner_revoked_all',
        );

        return response()->json([
            'message' => 'All active API tokens have been revoked.',
            'revoked_count' => $count,
        ]);
    }
}
