<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authorization\Models;

use App\Core\Foundation\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Permission extends BaseModel
{
    use SoftDeletes;

    protected $table = 'permissions';

    protected $casts = [
        'is_active' => 'boolean',
    ];
}