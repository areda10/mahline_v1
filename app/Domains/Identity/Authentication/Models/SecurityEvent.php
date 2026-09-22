<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\Models;

use App\Core\Foundation\Models\BaseModel;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SecurityEvent extends BaseModel
{
    protected $table = 'security_events';

    protected $fillable = [
        'user_id',
        'event',
        'reason',
        'ip_address',
        'user_agent',
        'browser',
        'device',
        'metadata',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'user_id',
        );
    }
}