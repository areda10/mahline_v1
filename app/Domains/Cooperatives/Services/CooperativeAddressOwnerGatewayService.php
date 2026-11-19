<?php

declare(strict_types=1);

namespace App\Domains\Cooperatives\Services;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Localization\Contracts\CooperativeAddressOwnerGateway;
use App\Domains\Localization\DTOs\CooperativeAddressOwnerData;
use Illuminate\Support\Facades\Gate;

final class CooperativeAddressOwnerGatewayService implements CooperativeAddressOwnerGateway
{
    public function lock(string $cooperativeId, bool $withTrashed = false): CooperativeAddressOwnerData
    {
        $query = $withTrashed ? Cooperative::withTrashed() : Cooperative::query();
        $cooperative = $query->whereKey($cooperativeId)->lockForUpdate()->firstOrFail();

        return new CooperativeAddressOwnerData((string) $cooperative->getKey());
    }

    public function authorize(string $actorId, string $ability, string $cooperativeId): void
    {
        $actor = User::query()->findOrFail($actorId);
        $cooperative = Cooperative::query()->findOrFail($cooperativeId);

        Gate::forUser($actor)->authorize($ability, $cooperative);
    }
}
