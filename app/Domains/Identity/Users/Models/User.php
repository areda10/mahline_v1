<?php

declare(strict_types=1);

namespace App\Domains\Identity\Users\Models;


use App\Core\Foundation\Models\BaseModel;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Authorization\Concerns\HasAuthorization;
use App\Domains\Identity\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class User extends BaseModel implements AuthenticatableContract
{
    use Authenticatable;
    use HasAuthorization;
    use HasFactory;
    use SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'display_name',
        'email',
        'telephone',
        'password',
        'status',
        'locale',
        'timezone',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'status' => UserStatus::class,
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'deleted_at' => 'datetime',
        ];
    }
    /**
     * Call factory
     */
    protected static function newFactory(): Factory
    {
        return UserFactory::new();
    }

    /** 
     * Relation btw User and Role
    */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'user_role',
            'user_id',
            'role_id',
        );
    }
}