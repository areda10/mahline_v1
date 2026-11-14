<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\Enums;

enum KnownDeviceStatus: string
{
    case Trusted = 'trusted';
    case Pending = 'pending';
    case NonTrusted = 'non_trusted';
}
