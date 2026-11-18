<?php

declare(strict_types=1);

namespace App\Domains\Products\Models;

use App\Core\Foundation\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ProductUnit extends BaseModel
{
    protected $table = 'product_units';

    protected $fillable = ['code', 'label', 'dimension', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_active' => 'boolean'];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'unit_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'unit_id');
    }
}
