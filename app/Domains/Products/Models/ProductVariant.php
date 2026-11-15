<?php

declare(strict_types=1);

namespace App\Domains\Products\Models;

use App\Core\Foundation\Models\BaseModel;
use App\Domains\Cooperatives\Models\Cooperative;
use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
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

    protected static function newFactory(): ProductVariantFactory
    {
        return ProductVariantFactory::new();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Resolve the cooperative through the owning product.
     *
     * Variants deliberately do not duplicate cooperative_id.
     */
    public function cooperative(): HasOneThrough
    {
        return $this->hasOneThrough(
            Cooperative::class,
            Product::class,
            'id',
            'id',
            'product_id',
            'cooperative_id',
        );
    }
}
