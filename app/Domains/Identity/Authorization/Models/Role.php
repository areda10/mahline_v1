<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authorization\Models;

use App\Core\Foundation\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Role extends BaseModel
{
    use SoftDeletes;

    protected $table = 'roles';

    protected $casts = [
        'is_active' => 'boolean',
    ];
}