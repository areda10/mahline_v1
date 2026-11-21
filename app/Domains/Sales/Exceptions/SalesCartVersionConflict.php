<?php

declare(strict_types=1);

namespace App\Domains\Sales\Exceptions;

use RuntimeException;

final class SalesCartVersionConflict extends RuntimeException
{
    public function __construct(public readonly int $currentVersion)
    {
        parent::__construct('Le panier a été modifié depuis son dernier chargement. Rechargez-le avant de réessayer.');
    }
}
