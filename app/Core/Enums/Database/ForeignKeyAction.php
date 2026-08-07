<?php

declare(strict_types=1);

namespace App\Core\Database\Enums;

use App\Core\Foundation\Enums\BaseEnum;

enum ForeignKeyAction: string
{
    use BaseEnum;

    case Cascade  = 'cascade';
    case Restrict = 'restrict';
    case NoAction = 'no action';
    case SetNull  = 'set null';

    public function label(): string
    {
        return match ($this) {
            self::Cascade  => 'Cascade',
            self::Restrict => 'Restrict',
            self::NoAction => 'No Action',
            self::SetNull  => 'Set Null',
        };
    }
}