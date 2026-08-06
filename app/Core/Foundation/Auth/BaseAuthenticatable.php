<?php

declare(strict_types=1);

namespace App\Core\Foundation\Auth;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

abstract class BaseAuthenticatable extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasUlids;
    use Notifiable;
    use SoftDeletes;

    /**
     * The primary key type.
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The attributes that should be hidden.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Common attribute casts.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'created_at'        => 'datetime',
            'updated_at'        => 'datetime',
            'deleted_at'        => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Authentication Helpers
    |--------------------------------------------------------------------------
    */

    public function isVerified(): bool
    {
        return $this->hasVerifiedEmail();
    }

    /*
    |--------------------------------------------------------------------------
    | Extension Points
    |--------------------------------------------------------------------------
    */

    public function revokeTokens(): void
    {
        // Implemented by the Identity domain.
    }

    public function revokeSessions(): void
    {
        // Implemented by the Identity domain.
    }

    public function changePassword(string $password): void
    {
        // Implemented by the Identity domain.
    }
}