<?php

declare(strict_types=1);

namespace App\Domains\Localization\Contracts;

use App\Domains\Localization\DTOs\CooperativeAddressOwnerData;

interface CooperativeAddressOwnerGateway
{
    /** Lock the cooperative owner row in the caller's transaction. */
    public function lock(string $cooperativeId, bool $withTrashed = false): CooperativeAddressOwnerData;

    public function authorize(string $actorId, string $ability, string $cooperativeId): void;
}
