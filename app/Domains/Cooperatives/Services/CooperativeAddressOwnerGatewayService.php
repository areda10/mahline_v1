<?php

declare(strict_types=1);

namespace App\Domains\Cooperatives\Services;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Localization\Contracts\CooperativeAddressOwnerGateway;
use App\Domains\Localization\DTOs\CooperativeAddressOwnerData;
use App\Shared\Contracts\ActorAuthorizer;

final class CooperativeAddressOwnerGatewayService implements CooperativeAddressOwnerGateway
{
    public function __construct(private readonly ActorAuthorizer $authorizer) {}

    public function lock(string $cooperativeId, bool $withTrashed = false): CooperativeAddressOwnerData
    {
        $query = $withTrashed ? Cooperative::withTrashed() : Cooperative::query();
        $cooperative = $query->whereKey($cooperativeId)->lockForUpdate()->firstOrFail();

        return new CooperativeAddressOwnerData((string) $cooperative->getKey());
    }

    public function authorize(string $actorId, string $ability, string $cooperativeId): void
    {
        $cooperative = Cooperative::query()->findOrFail($cooperativeId);

        $this->authorizer->authorize($actorId, $ability, $cooperative);
    }
}
