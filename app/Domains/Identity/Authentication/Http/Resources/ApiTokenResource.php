<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\Http\Resources;

use App\Domains\Identity\Authentication\Models\PersonalAccessToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PersonalAccessToken
 */
final class ApiTokenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PersonalAccessToken $token */
        $token = $this->resource;

        $isActive = $token->revoked_at === null
            && ($token->expires_at === null || $token->expires_at->isFuture());

        return [
            'id' => (string) $token->getKey(),
            'name' => $token->name,
            'abilities' => $token->abilities ?? [],
            'created_at' => $token->created_at?->toISOString(),
            'last_used_at' => $token->last_used_at?->toISOString(),
            'expires_at' => $token->expires_at?->toISOString(),
            'revoked_at' => $token->revoked_at?->toISOString(),
            'is_active' => $isActive,
        ];
    }
}
