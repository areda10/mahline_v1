<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authorization\Models;

use App\Core\Foundation\Models\BaseModel;
use App\Domains\Identity\Authorization\Models\Permission;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Role extends BaseModel
{
    use SoftDeletes;

    protected $table = 'roles';

    protected $casts = [
        'is_active' => 'boolean',
    ];
    
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            'role_permission',
            'role_id',
            'permission_id',
        );
    }

    /**
     * Relation btw Role and User
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'user_role',
            'role_id',
            'user_id',
        );
    }
}