<?php

declare(strict_types=1);

namespace App\Domains\Localization\Models;

use App\Core\Foundation\Models\BaseModel;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Localization\Models\Country;
use App\Domains\Identity\Users\Models\User;
use Database\Factories\AddressFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Address extends BaseModel
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'addresses';

    protected $fillable = [
        'number', 'street', 'floor', 'postal_code', 'city', 'region',
        'country_id', 'user_id', 'cooperative_id', 'latitude', 'longitude',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_primary' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    protected static function newFactory(): Factory
    {
        return AddressFactory::new();
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function cooperative(): BelongsTo
    {
        return $this->belongsTo(Cooperative::class, 'cooperative_id');
    }
}
