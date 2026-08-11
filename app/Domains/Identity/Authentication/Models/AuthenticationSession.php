<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\Models;

use App\Core\Foundation\Models\BaseModel;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class AuthenticationSession extends BaseModel
{
    use SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'authentication_sessions';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'session_id',
        'authenticated_at',
        'last_activity_at',
        'revoked_at',
        'revocation_reason',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'session_id',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'authenticated_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'revoked_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the authenticated user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'user_id',
            'id',
        );
    }

    /**
     * Determine whether the authentication session is active.
     */
    public function isActive(): bool
    {
        return $this->revoked_at === null
            && $this->deleted_at === null;
    }

    /**
     * Revoke the authentication session.
     */
    public function revoke(?string $reason = null): void
    {
        $this->forceFill([
            'revoked_at' => now(),
            'revocation_reason' => $reason,
        ])->save();
    }
}
