<?php

declare(strict_types=1);

namespace App\Domains\Cooperatives\Enums;

enum CooperativeStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case INACTIVE = 'inactive';
    case ARCHIVED = 'archived';
}
