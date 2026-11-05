<?php

declare(strict_types=1);

namespace App\Domains\Identity\UserManagement\Models;

use App\Core\Foundation\Models\BaseModel;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class UserInvitation extends BaseModel
{
    protected $table = 'user_invitations';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'revoked_at' => 'datetime',
            'created_at' => 'datetime',
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
