<?php

declare(strict_types=1);

namespace App\Domains\Media\Enums;

enum MediaIncidentStatus: string
{
    case OPEN = 'OPEN';
    case IN_PROGRESS = 'IN_PROGRESS';
    case RESOLVED = 'RESOLVED';
    case CLOSED = 'CLOSED';
}
