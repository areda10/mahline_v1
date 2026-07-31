<?php

declare(strict_types=1);

namespace App\Core\Foundation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

abstract class BaseModel extends Model
{
    use HasUlids;

    /**
     * La clé primaire est un ULID.
     */
    protected $keyType = 'string';

    /**
     * Désactive l'auto-incrément.
     */
    public $incrementing = false;

    /**
     * Active les timestamps Laravel.
     */
    public $timestamps = true;

    /**
     * Attributs protégés contre l'assignation massive.
     */
    protected $guarded = [];
}
