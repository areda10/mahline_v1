<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\Models;

use App\Core\Foundation\Models\BaseModel;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class KnownDevice extends BaseModel
{
    protected $table = 'known_devices';

    protected $fillable = [
        'user_id',
        'device',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'user_id',
        );
    }
}