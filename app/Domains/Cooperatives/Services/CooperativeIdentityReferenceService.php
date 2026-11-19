<?php

declare(strict_types=1);

namespace App\Domains\Cooperatives\Services;

use App\Domains\Cooperatives\Contracts\CooperativeIdentityReference;
use App\Domains\Cooperatives\Models\Cooperative;

final class CooperativeIdentityReferenceService implements CooperativeIdentityReference
{
    public function existsNotDeleted(string $cooperativeId): bool
    {
        return Cooperative::query()
            ->whereKey($cooperativeId)
            ->exists();
    }

    public function lockNotDeleted(string $cooperativeId): bool
    {
        return Cooperative::query()
            ->whereKey($cooperativeId)
            ->lockForUpdate()
            ->first() !== null;
    }
}
