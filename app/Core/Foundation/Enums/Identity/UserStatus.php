<?php

declare(strict_types=1);

namespace App\Core\Foundation\Enums\Identity;

use App\Core\Foundation\Enums\Contracts\BaseEnumContract;
use App\Core\Foundation\Enums\Traits\InteractsWithEnum;

enum UserStatus: string implements BaseEnumContract
{
    use InteractsWithEnum;

    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case SUSPENDED = 'suspended';
    case PENDING = 'pending';
    case ARCHIVED = 'archived';
}