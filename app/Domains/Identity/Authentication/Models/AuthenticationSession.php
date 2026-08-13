<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\Models;

use App\Core\Foundation\Models\BaseModel;
use App\Domains\Identity\Users\Models\User;
use Database\Factories\AuthenticationSessionFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class AuthenticationSession extends BaseModel
{
    use HasFactory;
    use SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'authentication_sessions';

    /**
     * The attributes that are mass assignable.
     *
     * IMPORTANT:
     *
     * session lifecycle attributes are intentionally fillable
     * because SessionService is responsible for managing them.
     */
    protected $fillable = [
        'user_id',
        'session_id',

        'ip_address',
        'user_agent',
        'browser',
        'device',

        'authenticated_at',
        'last_activity_at',
        'revoked_at',
        'revocation_reason',

        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be hidden
     * when the model is converted to an array.
     *
     * The Laravel session identifier is sensitive and must
     * never be exposed through normal API serialization.
     *
     * The raw User-Agent may also contain technical client
     * information and is therefore hidden.
     */
    protected $hidden = [
        'session_id',
        'user_agent',
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
     * Get the user who owns this authentication session.
     *
     * Every authentication session belongs to exactly one user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'user_id'
        );
    }

    /**
     * Determine whether this authentication session is active.
     *
     * MAHLINE authentication rule:
     *
     * ONE USER → ONE ACTIVE AUTHENTICATED SESSION
     *
     * A session is considered active when:
     *
     *     revoked_at === null
     *
     * Soft deletion is also considered inactive.
     */
    public function isActive(): bool
    {
        return $this->revoked_at === null
            && $this->deleted_at === null;
    }

    /**
     * Determine whether this authentication session has been revoked.
     */
    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /**
     * Determine whether this authentication session has been
     * soft deleted.
     */
    public function isDeleted(): bool
    {
        return $this->deleted_at !== null;
    }

    /**
     * Determine whether this authentication session is still
     * usable for authenticated activity.
     *
     * This method is intentionally stricter than simply checking
     * revoked_at.
     */
    public function isUsable(): bool
    {
        return $this->isActive();
    }

    /**
     * Mark the authentication session as revoked.
     *
     * SessionService should normally be used instead of calling
     * this method directly so that LoginHistoryService can record
     * the security event.
     */
    public function revoke(
        string $reason,
    ): bool {
        if ($this->isRevoked()) {
            return false;
        }

        $this->forceFill([
            'revoked_at' => now(),
            'revocation_reason' => $reason,
        ]);

        return $this->save();
    }

    /**
     * Update the last activity timestamp.
     *
     * An inactive or revoked session cannot be touched.
     */
    public function touchActivity(): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        $this->forceFill([
            'last_activity_at' => now(),
        ]);

        return $this->save();
    }

    /**
     * Create the model factory.
     */
    protected static function newFactory(): Factory
    {
        return AuthenticationSessionFactory::new();
    }
}