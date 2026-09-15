<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authorization\Models;

use App\Core\Foundation\Models\BaseModel;
use App\Domains\Identity\Authorization\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Permission extends BaseModel
{
    use SoftDeletes;

    protected $table = 'permissions';

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'role_permission',
            'permission_id',
            'role_id',
        );
    }
}