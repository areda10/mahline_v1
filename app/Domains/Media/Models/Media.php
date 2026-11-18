<?php

declare(strict_types=1);

namespace App\Domains\Media\Models;

use App\Core\Foundation\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Media extends BaseModel
{
    use SoftDeletes;

    protected $table = 'media';

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'is_primary' => 'boolean',
            'sort_order' => 'integer',
            'metadata' => 'array',
            'deleted_at' => 'datetime',
        ];
    }

    public function mediable(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }
}
