<?php

declare(strict_types=1);

namespace App\Domains\Orders\Exceptions;

use RuntimeException;

final class OrderFulfillmentBlocked extends RuntimeException
{
}
