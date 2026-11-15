<?php

declare(strict_types=1);

namespace App\Domains\Cooperatives\Models;

use App\Core\Foundation\Models\BaseModel;
use App\Domains\Cooperatives\Enums\CooperativeStatus;
use App\Domains\Identity\Authorization\Models\SuperAdminCooperative;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Localization\Models\Address;
use App\Domains\Media\Models\Media;
use App\Domains\Products\Models\Product;
use Database\Factories\CooperativeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
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

    /**
     * Restrict cooperative queries to the records visible to the given user.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->isAuthorized()) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasRole('super_admin_root')) {
            return $query;
        }

        if ($user->hasRole('super_admin')) {
            return $query->whereIn(
                $this->getQualifiedKeyName(),
                $user->cooperatives()->select('cooperatives.id'),
            );
        }

        if ($user->hasAnyRole(['cooperative_admin', 'user']) && $user->cooperative_id !== null) {
            return $query->where($this->getQualifiedKeyName(), $user->cooperative_id);
        }

        return $query->whereRaw('1 = 0');
    }

    /**
     * Products owned by this cooperative.
     *
     * ProductVariant ownership is inherited through Product::cooperative_id.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'cooperative_id');
    }

    /**
     * Active products owned by this cooperative.
     */
    public function activeProducts(): HasMany
    {
        return $this->products()->where('is_active', true);
    }

    public function settings(): HasOne
    {
        return $this->hasOne(
            CooperativeSettings::class,
            'cooperative_id',
        );
    }

    public function address(): HasOne
    {
        return $this->hasOne(
            Address::class,
            'cooperative_id',
        );
    }

    public function superAdmins(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'super_admin_cooperative',
            'cooperative_id',
            'super_admin_id',
        )
            ->using(SuperAdminCooperative::class)
            ->withTimestamps();
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    public function logo(): MorphOne
    {
        return $this->morphOne(Media::class, 'mediable')
            ->where('category', 'cooperative_logo')
            ->where('is_primary', true);
    }

    public function photos(): MorphMany
    {
        return $this->media()->where('category', 'cooperative_photo')
            ->orderBy('sort_order')
            ->orderBy('created_at');
    }

    public function documents(): MorphMany
    {
        return $this->media()->where('category', 'document')
            ->orderBy('sort_order')
            ->orderBy('created_at');
    }
}
