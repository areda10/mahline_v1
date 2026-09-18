<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

final class PersonalAccessToken extends SanctumPersonalAccessToken
{
    use HasUlids;

    protected $table = 'personal_access_tokens';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = true;
}