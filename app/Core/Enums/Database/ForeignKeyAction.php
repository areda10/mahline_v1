<?php

declare(strict_types=1);

namespace App\Core\Enums\Database;

use App\Core\Foundation\Enums\BaseEnum;
use App\Core\Foundation\Enums\Contracts\BaseEnumContract;

enum ForeignKeyAction: string implements BaseEnumContract
{
    use BaseEnum;

    case CASCADE = 'cascade';

    case RESTRICT = 'restrict';

    case SET_NULL = 'set_null';

    case NO_ACTION = 'no_action';
}