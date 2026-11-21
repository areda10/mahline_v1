<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Exceptions;

use RuntimeException;

final class InsufficientInventoryStock extends RuntimeException
{
    public function __construct(
        public readonly string $stockableId,
        public readonly string $requested,
        public readonly string $available,
    ) {
        parent::__construct('Insufficient inventory stock for the requested item.');
    }
}
