<?php

declare(strict_types=1);

namespace App\Domains\Localization\Models;

use App\Core\Foundation\Models\BaseModel;
use Database\Factories\AddressFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Address extends BaseModel
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'addresses';

    protected $fillable = [
        'address_line_1',
        'address_line_2',
        'postal_code',
        'city',
        'state',
        'country_id',
        'latitude',
        'longitude',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'deleted_at' => 'datetime',
        ];
    }

    protected static function newFactory(): Factory
    {
        return AddressFactory::new();
    }
}