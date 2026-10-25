<?php

declare(strict_types=1);

namespace App\Domains\Products\Models;

use App\Core\Foundation\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class ProductVariant extends BaseModel
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'product_variants';

    protected $fillable = [
        'product_id',
        'label',
        'normalized_label',
        'sku',
        'attributes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'is_active' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}