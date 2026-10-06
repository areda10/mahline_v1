<?php

declare(strict_types=1);

namespace App\Domains\Countries\Models;

use App\Core\Foundation\Models\BaseModel;
use Database\Factories\CountryFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Country extends BaseModel
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'countries';

    protected $fillable = [
        'code',
        'iso3',
        'name',
        'native_name',
        'phone_code',
        'currency',
        'locale',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    protected static function newFactory(): Factory
    {
        return CountryFactory::new();
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(
            \App\Domains\Localization\Models\Address::class,
            'country_id',
        );
    }
}