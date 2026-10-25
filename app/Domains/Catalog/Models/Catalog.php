<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Models;

use App\Core\Foundation\Models\BaseModel;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Products\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Catalog extends BaseModel
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'catalogs';

    protected $fillable = [
        'cooperative_id',
        'name',
        'slug',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    public function cooperative(): BelongsTo
    {
        return $this->belongsTo(
            Cooperative::class,
            'cooperative_id'
        );
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'catalog_product',
            'catalog_id',
            'product_id'
        )->withTimestamps();
    }

    public function scopeForCooperative(
        Builder $query,
        string $cooperativeId
    ): Builder {
        return $query->where('cooperative_id', $cooperativeId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    protected static function newFactory(): \Database\Factories\CatalogFactory
    {
        return \Database\Factories\CatalogFactory::new();
    }
}
