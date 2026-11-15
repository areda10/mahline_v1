<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authorization\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\Pivot;

final class SuperAdminCooperative extends Pivot
{
    use HasUlids;

    protected $table = 'super_admin_cooperative';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'super_admin_id',
        'cooperative_id',
    ];
}
