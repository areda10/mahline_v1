<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Models;

use App\Core\Foundation\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class InventoryStockThreshold extends BaseModel
{
    protected $table = 'inventory_stock_thresholds';

    protected $fillable = ['stock_id', 'low_stock_threshold', 'updated_by'];

    protected function casts(): array
    {
        return ['low_stock_threshold' => 'decimal:3'];
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(InventoryStock::class, 'stock_id');
    }
}
