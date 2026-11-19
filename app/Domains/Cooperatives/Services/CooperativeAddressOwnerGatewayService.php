<?php

declare(strict_types=1);

namespace App\Domains\Cooperatives\Services;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Localization\Contracts\CooperativeAddressOwnerGateway;
use App\Domains\Localization\DTOs\CooperativeAddressOwnerData;
use App\Domains\Localization\Policies\AddressPolicy;
use App\Shared\Contracts\ActorContextProvider;
use Illuminate\Auth\Access\AuthorizationException;

final class CooperativeAddressOwnerGatewayService implements CooperativeAddressOwnerGateway
{
    public function __construct(
        private readonly ActorContextProvider $actors,
        private readonly AddressPolicy $policy,
    ) {}

    public function lock(string $cooperativeId, bool $withTrashed = false): CooperativeAddressOwnerData
    {
        $query = $withTrashed ? Cooperative::withTrashed() : Cooperative::query();
        $cooperative = $query->whereKey($cooperativeId)->lockForUpdate()->firstOrFail();

        return new CooperativeAddressOwnerData((string) $cooperative->getKey());
    }

    public function authorize(string $actorId, string $ability, string $cooperativeId): void
    {
        $actor = $this->actors->forUserId($actorId);
        $cooperative = new CooperativeAddressOwnerData($cooperativeId);

        $allowed = match ($ability) {
            'createCooperativeAddress' => $this->policy->createCooperativeAddress($actor, $cooperative),
            'updateCooperativeAddress' => $this->policy->updateCooperativeAddress($actor, $cooperative),
            default => throw new \InvalidArgumentException('Unsupported cooperative address authorization ability.'),
        };

        if (! $allowed) {
            throw new AuthorizationException;
        }
    }
}
