<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\Models;

use App\Domains\Identity\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

final class PersonalAccessToken extends SanctumPersonalAccessToken
{
    use HasUlids;

    public const int EXPIRATION_DAYS = 90;

    /**
     * Enforce the MAHLINE API-token lifetime for every creation path,
     * including direct calls to User::createToken().
     */
    protected static function booted(): void
    {
        static::creating(static function (self $token): void {
            $token->expires_at = now()->addDays(self::EXPIRATION_DAYS);
        });
    }

    protected $table = 'personal_access_tokens';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = true;

    protected function casts(): array
    {
        return [
            'abilities' => 'json',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * Sanctum resolves bearer credentials through this method.
     * A revoked token must never be returned as an authenticated token.
     */
    public static function findToken($token): ?static
    {
        $personalAccessToken = parent::findToken($token);

        if (
            $personalAccessToken === null
            || $personalAccessToken->revoked_at !== null
        ) {
            return null;
        }

        // API credentials are valid only while their owning account is authorized.
        // The tokenable relation also respects User soft deletes.
        $tokenable = $personalAccessToken->tokenable;

        if (
            ! $tokenable instanceof User
            || ! $tokenable->isAuthorized()
        ) {
            return null;
        }

        return $personalAccessToken;
    }
}
