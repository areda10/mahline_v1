<?php

declare(strict_types=1);

namespace App\Domains\Orders\Models;

use App\Core\Foundation\Models\BaseModel;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OrderStatusHistory extends BaseModel
{
    protected $table = 'order_status_histories';

    public $timestamps = false;

    protected $fillable = [
        'subject_type', 'subject_id', 'from_status', 'to_status',
        'actor_id', 'actor_role', 'reason', 'metadata', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}