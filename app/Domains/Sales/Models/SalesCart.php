<?php

declare(strict_types=1);

namespace App\Domains\Sales\Models;

use App\Core\Foundation\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SalesCart extends BaseModel
{
    protected $table = 'sales_carts';

    protected $fillable = [
        'customer_id',
        'guest_token_hash',
        'version',
        'last_activity_at',
        'expires_at',
        'merged_into_cart_id',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'last_activity_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesCartItem::class, 'cart_id');
    }

    public function activeItems(): HasMany
    {
        return $this->items()->where('status', 'active')->whereNull('removed_at');
    }
}
