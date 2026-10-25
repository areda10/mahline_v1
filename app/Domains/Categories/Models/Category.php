<?php

declare(strict_types=1);

namespace App\Domains\Categories\Models;

use App\Core\Foundation\Models\BaseModel;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Products\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Category extends BaseModel
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'categories';

    protected $fillable = [
        'cooperative_id',
        'parent_id',
        'name',
        'slug',
        'description',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }

    public function cooperative(): BelongsTo
    {
        return $this->belongsTo(Cooperative::class, 'cooperative_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'category_product',
            'category_id',
            'product_id',
        )->withTimestamps();
    }

    /**
     * Catégories globales + catégories de la coopérative.
     */
    public function scopeAvailableFor(
        Builder $query,
        string $cooperativeId,
    ): Builder {
        return $query->where(function (Builder $query) use ($cooperativeId): void {
            $query->whereNull('cooperative_id')
                ->orWhere('cooperative_id', $cooperativeId);
        });
    }

    public function scopeGlobal(Builder $query): Builder
    {
        return $query->whereNull('cooperative_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isGlobal(): bool
    {
        return $this->cooperative_id === null;
    }

    public function belongsToCooperative(string $cooperativeId): bool
    {
        return $this->cooperative_id !== null
            && (string) $this->cooperative_id === $cooperativeId;
    }
}