<?php

declare(strict_types=1);

namespace App\Domains\Products\Models;

use App\Core\Foundation\Models\BaseModel;
use App\Domains\Catalog\Models\Catalog;
use App\Domains\Categories\Models\Category;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Media\Models\Media;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Product extends BaseModel
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'products';

    protected $fillable = [
        'cooperative_id',
        'name',
        'slug',
        'description',
        'price',
        'currency',
        'unit_id',
        'is_active',
    ];

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'price' => 'decimal:2',
            'deleted_at' => 'datetime',
        ];
    }

    public function cooperative(): BelongsTo
    {
        return $this->belongsTo(
            Cooperative::class,
            'cooperative_id',
        );
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'unit_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'product_id');
    }

    /**
     * Active variants only. Inactive and soft-deleted variants remain
     * available through variants() for administration and historical review.
     */
    public function activeVariants(): HasMany
    {
        return $this->variants()
            ->where('is_active', true);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            Category::class,
            'category_product',
            'product_id',
            'category_id',
        )->withTimestamps();
    }

    public function catalogs(): BelongsToMany
    {
        return $this->belongsToMany(
            Catalog::class,
            'catalog_product',
            'product_id',
            'catalog_id',
        )->withTimestamps();
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    public function images(): MorphMany
    {
        return $this->media()->where('category', 'product_image')
            ->orderBy('sort_order')
            ->orderBy('created_at');
    }

    public function primaryImage(): MorphOne
    {
        return $this->morphOne(Media::class, 'mediable')
            ->where('category', 'product_image')
            ->where('is_primary', true);
    }

    public function documents(): MorphMany
    {
        return $this->media()->where('category', 'document')
            ->orderBy('sort_order')
            ->orderBy('created_at');
    }

    public function scopeForCooperative(
        Builder $query,
        string $cooperativeId,
    ): Builder {
        return $query->where(
            'cooperative_id',
            $cooperativeId,
        );
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
