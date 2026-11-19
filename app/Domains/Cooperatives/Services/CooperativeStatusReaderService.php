<?php

declare(strict_types=1);

namespace App\Domains\Cooperatives\Services;

use App\Domains\Cooperatives\Contracts\CooperativeStatusReader;
use App\Domains\Cooperatives\DTOs\CooperativeStatusData;
use App\Domains\Cooperatives\Enums\CooperativeStatus;
use App\Domains\Cooperatives\Models\Cooperative;

final class CooperativeStatusReaderService implements CooperativeStatusReader
{
    public function findStatus(string $cooperativeId): ?CooperativeStatusData
    {
        $cooperative = Cooperative::withTrashed()->find($cooperativeId);

        if ($cooperative === null) {
            return null;
        }

        return new CooperativeStatusData(
            cooperativeId: (string) $cooperative->getKey(),
            status: $cooperative->status->value,
            isActive: ! $cooperative->trashed()
                && $cooperative->status === CooperativeStatus::ACTIVE,
            isDeleted: $cooperative->trashed(),
        );
    }
}
