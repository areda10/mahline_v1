<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\Models;

use App\Core\Foundation\Models\BaseModel;
use App\Domains\Identity\Users\Models\User;
use Database\Factories\LoginHistoryFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class LoginHistory extends BaseModel
{
    use HasFactory;
    use SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'login_histories';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'email',
        'event',
        'reason',
        'authentication_session_id',
        'ip_address',
        'user_agent',
        'browser',
        'device',
        'occurred_at',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be hidden
     * when the model is converted to an array.
     */
    protected $hidden = [
        'user_agent',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the user associated with this login history entry.
     *
     * A login history may have no user when the authentication
     * attempt was made with an unknown email address.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'user_id'
        );
    }

    /**
     * Get the authentication session associated with this event.
     *
     * The relation is intentionally nullable because failed
     * authentication attempts do not create a session.
     */
    public function authenticationSession(): BelongsTo
    {
        return $this->belongsTo(
            AuthenticationSession::class,
            'authentication_session_id'
        );
    }

    /**
     * Create the model factory.
     */
    protected static function newFactory(): Factory
    {
        return LoginHistoryFactory::new();
    }
}