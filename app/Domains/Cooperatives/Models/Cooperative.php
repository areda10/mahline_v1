<?php

declare(strict_types=1);

namespace App\Domains\Cooperatives\Models;

use App\Core\Foundation\Models\BaseModel;
use App\Domains\Cooperatives\Enums\CooperativeStatus;
use App\Domains\Localization\Models\Address;
use Database\Factories\CooperativeFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Cooperative extends BaseModel
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'cooperatives';

    protected $fillable = [
        'name',
        'rib',
        'tax_id',
        'address_id',
        'phone',
        'email',
        'rc',
        'ice',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => CooperativeStatus::class,
            'deleted_at' => 'datetime',
        ];
    }

    protected static function newFactory(): Factory
    {
        return CooperativeFactory::new();
    }

    public function settings(): HasOne
    {
        return $this->hasOne(
            CooperativeSettings::class,
            'cooperative_id',
        );
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(
            Address::class,
            'address_id',
        );
    }

}
