<?php

declare(strict_types=1);

namespace App\Domains\Cooperatives\Contracts;

interface CooperativeIdentityReference
{
    public function existsNotDeleted(string $cooperativeId): bool;

    /**
     * Lock an active cooperative row for the duration of the caller's transaction.
     */
    public function lockNotDeleted(string $cooperativeId): bool;
}
