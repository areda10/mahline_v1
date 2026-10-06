<?php

declare(strict_types=1);

namespace App\Domains\Cooperatives\Models;

use App\Core\Foundation\Models\BaseModel;
use Database\Factories\CooperativeSettingsFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class CooperativeSettings extends BaseModel
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'cooperative_settings';

    protected $fillable = [
        'cooperative_id',
        'currency',
        'locale',
        'timezone',
        'order_prefix',
        'invoice_prefix',
        'default_tax_rate',
        'prices_include_tax',
        'notify_new_order',
        'notify_order_status',
        'notify_low_stock',
    ];

    protected function casts(): array
    {
        return [
            'default_tax_rate' => 'decimal:2',
            'prices_include_tax' => 'boolean',
            'notify_new_order' => 'boolean',
            'notify_order_status' => 'boolean',
            'notify_low_stock' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    protected static function newFactory(): Factory
    {
        return CooperativeSettingsFactory::new();
    }

    public function cooperative(): BelongsTo
    {
        return $this->belongsTo(
            Cooperative::class,
            'cooperative_id',
        );
    }
}