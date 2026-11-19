<?php

declare(strict_types=1);

namespace App\Domains\Localization\Contracts;

use App\Domains\Localization\DTOs\UserAddressOwnerData;

interface UserAddressOwnerGateway
{
    /** Locks the owner row for the caller's transaction. */
    public function lock(string $userId): UserAddressOwnerData;

    public function authorize(string $actorId, string $ability, string $userId): void;

    public function cooperativeId(string $userId): ?string;
}
