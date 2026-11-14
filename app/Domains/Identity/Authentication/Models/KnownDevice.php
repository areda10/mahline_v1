<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\Models;

use App\Core\Foundation\Models\BaseModel;
use App\Domains\Identity\Authentication\Enums\KnownDeviceStatus;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class KnownDevice extends BaseModel
{
    protected $table = 'known_devices';

    protected $fillable = [
        'user_id',
        'device',
        'browser',
        'device_token_hash',
        'status',
        'pending_until',
        'trusted_at',
        'last_seen_at',
        'revoked_at',
    ];

    protected $hidden = [
        'device_token_hash',
    ];

    protected function casts(): array
    {
        return [
            'status' => KnownDeviceStatus::class,
            'pending_until' => 'datetime',
            'trusted_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(AuthenticationSession::class, 'known_device_id');
    }

    public function latestSession(): HasOne
    {
        return $this->hasOne(AuthenticationSession::class, 'known_device_id')
            ->latestOfMany('authenticated_at');
    }

    public function isTrusted(): bool
    {
        return $this->status === KnownDeviceStatus::Trusted;
    }

    public function isPending(): bool
    {
        return $this->status === KnownDeviceStatus::Pending;
    }

    public function isRevoked(): bool
    {
        return $this->status === KnownDeviceStatus::NonTrusted;
    }
}
